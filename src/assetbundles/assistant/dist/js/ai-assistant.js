(function() {
    window.LauncherAI = {
        messageInput: null,
        messagesContainer: null,
        sendButton: null,
        config: {
            sendMessageUrl: '',
            startConversationUrl: '',
            validateUrl: '',
        },
        currentThreadId: null,
        isInitialized: false,
        isSending: false,

        init: function(config) {
            if (this.isInitialized) {
                return;
            }

            Object.assign(this.config, config);

            // Wait for elements to be available (they're in the tab)
            const self = this;
            const checkElements = setInterval(function() {
                self.messageInput = document.getElementById('launcher-ai-input');
                self.messagesContainer = document.getElementById('launcher-ai-messages');
                self.sendButton = document.getElementById('launcher-ai-send');

                if (self.messageInput && self.messagesContainer && self.sendButton) {
                    clearInterval(checkElements);
                    self.bindEvents();
                    self.isInitialized = true;

                    // Validate provider configuration
                    self.validateProvider();
                }
            }, 100);

            // Stop checking after 5 seconds
            setTimeout(function() {
                clearInterval(checkElements);
            }, 5000);
        },

        bindEvents: function() {
            const self = this;

            // Handle Enter in textarea (Shift+Enter for new line)
            this.messageInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    self.sendMessage();
                }
            });

            // Send button
            this.sendButton.addEventListener('click', function() {
                self.sendMessage();
            });

            // Suggestion buttons - use event delegation on the container
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('launcher-ai-suggestion')) {
                    const prompt = e.target.getAttribute('data-prompt');
                    self.messageInput.value = prompt;
                    self.messageInput.focus();
                    self.sendMessage();
                }
            });

            // Auto-resize textarea
            this.messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 200) + 'px';
            });

            // Listen for tab changes to start conversation when assistant tab is opened
            document.addEventListener('launcherTabChanged', function(e) {
                if (e.detail && e.detail.tab === 'assistant') {
                    // Focus input and scroll to bottom
                    self.focusInput();
                    self.scrollToBottom();

                    // Start conversation if needed
                    if (!self.currentThreadId) {
                        self.startConversation();
                    }
                }
            });
        },

        validateProvider: function() {
            const self = this;

            fetch(this.config.validateUrl, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (!data.configured) {
                    console.warn('Launcher AI: Provider not configured -', data.message);
                }
            })
            .catch(error => {
                console.error('Launcher AI: Validation error:', error);
            });
        },

        startConversation: function() {
            const self = this;

            fetch(this.config.startConversationUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': window.Craft.csrfTokenValue || '',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    self.currentThreadId = data.conversation.threadId;

                    // Load existing messages if any
                    if (data.messages && data.messages.length > 0) {
                        self.clearWelcome();
                        data.messages.forEach(msg => {
                            self.addMessage(msg.role, msg.content);
                        });
                    }
                } else {
                    self.showError('Failed to start conversation: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Launcher AI: Start conversation error:', error);
                self.showError('Failed to connect to Astronaut');
            });
        },

        startNewConversation: function() {
            this.currentThreadId = null;
            this.messagesContainer.innerHTML = `
                <div class="launcher-ai-welcome">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M9 9h.01M15 9h.01M9 15a3 3 0 0 0 6 0"></path>
                    </svg>
                    <h3>New Conversation</h3>
                    <p>How can I help you today?</p>
                </div>
            `;
            this.startConversation();
        },

        sendMessage: function() {
            if (this.isSending) return;

            const message = this.messageInput.value.trim();
            if (!message) return;

            if (!this.currentThreadId) {
                this.showError('No active conversation. Please wait...');
                return;
            }

            this.isSending = true;
            this.clearWelcome();
            this.addMessage('user', message);
            this.messageInput.value = '';
            this.messageInput.style.height = 'auto';

            // Show typing indicator
            const typingId = this.showTyping();

            const self = this;
            fetch(this.config.sendMessageUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': window.Craft.csrfTokenValue || '',
                },
                body: JSON.stringify({
                    threadId: this.currentThreadId,
                    message: message,
                })
            })
            .then(response => response.json())
            .then(data => {
                self.isSending = false;
                self.removeTyping(typingId);

                if (data.success && data.message) {
                    self.addMessage('assistant', data.message.content);
                } else {
                    self.showError(data.error || 'Failed to send message');
                }
            })
            .catch(error => {
                self.isSending = false;
                self.removeTyping(typingId);
                console.error('Launcher AI: Send message error:', error);
                self.showError('Failed to send message. Please try again.');
            });
        },

        clearWelcome: function() {
            const welcome = this.messagesContainer.querySelector('.launcher-ai-welcome');
            if (welcome) {
                welcome.remove();
            }
        },

        addMessage: function(role, content) {
            const messageEl = document.createElement('div');
            messageEl.className = `launcher-ai-message launcher-ai-message-${role}`;

            const avatar = document.createElement('div');
            avatar.className = 'launcher-ai-message-avatar';
            avatar.innerHTML = role === 'user'
                ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>'
                : '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>';

            const bubble = document.createElement('div');
            bubble.className = 'launcher-ai-message-content';

            // Format content
            if (role === 'assistant') {
                // AI responses are HTML from a trusted source, render directly
                bubble.innerHTML = this.sanitizeHtml(content);
            } else {
                // User messages are plain text, escape for safety
                bubble.textContent = content;
            }

            messageEl.appendChild(avatar);
            messageEl.appendChild(bubble);
            this.messagesContainer.appendChild(messageEl);

            // Scroll to bottom
            this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
        },

        sanitizeHtml: function(html) {
            if (!html) return '';

            // Create a temporary div to parse HTML
            const temp = document.createElement('div');
            temp.innerHTML = html;

            // List of allowed tags
            const allowedTags = ['P', 'BR', 'STRONG', 'EM', 'B', 'I', 'U', 'CODE', 'PRE',
                               'H1', 'H2', 'H3', 'H4', 'H5', 'H6',
                               'UL', 'OL', 'LI', 'A', 'SPAN', 'DIV', 'BLOCKQUOTE'];

            // List of allowed attributes
            const allowedAttributes = {
                'A': ['href', 'target', 'rel'],
                'SPAN': ['class'],
                'DIV': ['class']
            };

            // Recursive function to sanitize nodes
            const sanitizeNode = (node) => {
                // If it's a text node, return it as-is
                if (node.nodeType === Node.TEXT_NODE) {
                    return node.cloneNode();
                }

                // If it's an element node
                if (node.nodeType === Node.ELEMENT_NODE) {
                    const tagName = node.tagName.toUpperCase();

                    // If tag is not allowed, return its children
                    if (!allowedTags.includes(tagName)) {
                        const fragment = document.createDocumentFragment();
                        Array.from(node.childNodes).forEach(child => {
                            const sanitized = sanitizeNode(child);
                            if (sanitized) fragment.appendChild(sanitized);
                        });
                        return fragment;
                    }

                    // Create clean element
                    const cleanElement = document.createElement(node.tagName);

                    // Copy allowed attributes
                    if (allowedAttributes[tagName]) {
                        allowedAttributes[tagName].forEach(attr => {
                            if (node.hasAttribute(attr)) {
                                let value = node.getAttribute(attr);

                                // Extra sanitization for href
                                if (attr === 'href') {
                                    // Only allow http, https, and relative URLs
                                    if (!value.match(/^(https?:\/\/|\/)/i)) {
                                        return; // Skip this attribute
                                    }
                                    // Ensure target="_blank" for external links
                                    if (value.match(/^https?:\/\//i)) {
                                        cleanElement.setAttribute('target', '_blank');
                                        cleanElement.setAttribute('rel', 'noopener noreferrer');
                                    }
                                }

                                cleanElement.setAttribute(attr, value);
                            }
                        });
                    }

                    // Recursively sanitize children
                    Array.from(node.childNodes).forEach(child => {
                        const sanitized = sanitizeNode(child);
                        if (sanitized) cleanElement.appendChild(sanitized);
                    });

                    return cleanElement;
                }

                return null;
            };

            // Sanitize all child nodes
            const sanitizedFragment = document.createDocumentFragment();
            Array.from(temp.childNodes).forEach(node => {
                const sanitized = sanitizeNode(node);
                if (sanitized) sanitizedFragment.appendChild(sanitized);
            });

            // Convert back to HTML string
            const sanitizedDiv = document.createElement('div');
            sanitizedDiv.appendChild(sanitizedFragment);
            return sanitizedDiv.innerHTML;
        },

        showTyping: function() {
            const typingId = 'typing-' + Date.now();
            const typingEl = document.createElement('div');
            typingEl.id = typingId;
            typingEl.className = 'launcher-ai-message launcher-ai-message-assistant';
            typingEl.innerHTML = `
                <div class="launcher-ai-message-avatar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                </div>
                <div class="launcher-ai-message-content launcher-ai-typing">
                    <span></span><span></span><span></span>
                </div>
            `;
            this.messagesContainer.appendChild(typingEl);
            this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
            return typingId;
        },

        removeTyping: function(typingId) {
            const el = document.getElementById(typingId);
            if (el) {
                el.remove();
            }
        },

        showError: function(message) {
            const errorEl = document.createElement('div');
            errorEl.className = 'launcher-ai-error';
            errorEl.textContent = message;
            this.messagesContainer.appendChild(errorEl);
            this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;

            // Auto-remove after 5 seconds
            setTimeout(function() {
                errorEl.remove();
            }, 5000);
        },

        loadConversationList: function() {
            const container = document.getElementById('launcher-ai-drawer-conversations');
            if (!container) return;

            const self = this;

            fetch('/actions/astronaut/ai/list', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.conversations) {
                    self.renderConversationList(data.conversations, container);
                } else {
                    container.innerHTML = '<p style="padding: 12px; color: #64748b;">No conversations yet</p>';
                }
            })
            .catch(error => {
                console.error('Failed to load conversations:', error);
                container.innerHTML = '<p style="padding: 12px; color: #cf1124;">Failed to load conversations</p>';
            });
        },

        renderConversationList: function(conversations, container) {
            if (!conversations || conversations.length === 0) {
                container.innerHTML = '<p style="padding: 12px; color: #64748b;">No conversations yet</p>';
                return;
            }

            const self = this;
            let html = '<div style="display: flex; flex-direction: column; gap: 4px;">';

            conversations.forEach(conv => {
                const isActive = conv.id === self.currentThreadId;
                const activeClass = isActive ? ' style="background: #e0f2fe; border-color: #0369a1;"' : '';
                const date = new Date(conv.dateCreated * 1000);
                const dateStr = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

                html += `
                    <button
                        class="launcher-ai-conversation-item"
                        data-conversation-id="${conv.id}"
                        ${activeClass}
                        style="
                            display: block;
                            width: 100%;
                            text-align: left;
                            padding: 8px 12px;
                            border: 1px solid var(--hairline-color, #e3e5e8);
                            border-radius: 4px;
                            background: #fff;
                            cursor: pointer;
                            transition: all 0.15s ease;
                            font-size: 13px;
                        "
                    >
                        <div style="font-weight: 500; margin-bottom: 2px;">Conversation ${conv.id}</div>
                        <div style="font-size: 11px; color: #64748b;">${dateStr}</div>
                    </button>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
            container.removeAttribute('data-loading');

            // Bind click handlers
            container.querySelectorAll('.launcher-ai-conversation-item').forEach(btn => {
                btn.addEventListener('click', function() {
                    const convId = parseInt(this.getAttribute('data-conversation-id'));
                    self.switchConversation(convId);
                });
            });
        },

        switchConversation: function(conversationId) {
            const self = this;

            // Clear current messages
            this.messagesContainer.innerHTML = '<div class="launcher-ai-welcome"><h3>Astronaut</h3></div>';
            this.currentThreadId = conversationId;

            // Load conversation history
            fetch(`/actions/astronaut/ai/history?conversationId=${conversationId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.messages) {
                    // Clear welcome message
                    self.messagesContainer.innerHTML = '';

                    // Add all messages
                    data.messages.forEach(msg => {
                        if (msg.role === 'user' || msg.role === 'assistant') {
                            self.addMessage(msg.role, msg.content);
                        }
                    });

                    self.scrollToBottom();
                }
            })
            .catch(error => {
                console.error('Failed to load conversation:', error);
                self.showError('Failed to load conversation');
            });

            // Reload conversation list to update active state
            this.loadConversationList();
        },

        handleNewChat: function() {
            const self = this;

            // Clear current thread
            this.currentThreadId = null;
            this.messagesContainer.innerHTML = '<div class="launcher-ai-welcome"><h3>Astronaut</h3></div>';
            this.focusInput();

            // Start new conversation
            this.startConversation();

            // Reload conversation list
            setTimeout(() => {
                self.loadConversationList();
            }, 500);
        },

        scrollToBottom: function() {
            if (this.messagesContainer) {
                this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
            }
        },

        focusInput: function() {
            if (this.messageInput) {
                setTimeout(() => {
                    this.messageInput.focus();
                }, 100);
            }
        },
    };

    // Initialize drawer integration when drawer opens
    document.addEventListener('DOMContentLoaded', function() {
        // Watch for drawer opening in assistant context
        document.addEventListener('click', function(e) {
            // When drawer toggle is clicked
            if (e.target.closest('.launcher-drawer-toggle')) {
                setTimeout(() => {
                    if (window.LauncherAI && window.LauncherAI.isInitialized) {
                        window.LauncherAI.loadConversationList();
                    }
                }, 100);
            }

            // Handle new chat button in drawer
            if (e.target.closest('#launcher-ai-new-chat-action')) {
                if (window.LauncherAI && window.LauncherAI.isInitialized) {
                    window.LauncherAI.handleNewChat();
                }
            }
        });
    });
})();
