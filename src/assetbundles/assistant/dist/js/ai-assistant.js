(function() {
    console.log('[ASTRONAUT] ai-assistant.js loaded');

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
            console.log('[ASTRONAUT] init() called with config:', config);

            if (this.isInitialized) {
                console.log('[ASTRONAUT] Already initialized, skipping');
                return;
            }

            Object.assign(this.config, config);
            console.log('[ASTRONAUT] Config set, waiting for elements...');

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

            // Auto-close drawer when input is focused
            this.messageInput.addEventListener('focus', function() {
                console.log('[ASTRONAUT] Input focused, checking if drawer should close');
                const drawer = document.querySelector('.launcher-drawer');
                if (drawer && drawer.classList.contains('launcher-drawer-open')) {
                    console.log('[ASTRONAUT] Closing drawer');
                    // Use the Launcher plugin's method to close drawer
                    if (window.LauncherPlugin && window.LauncherPlugin.closeDrawer) {
                        window.LauncherPlugin.closeDrawer();
                    }
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
            if (!container) {
                console.error('Conversation container not found');
                return;
            }

            const self = this;
            container.innerHTML = '<p style="padding: 12px; color: #64748b;">Loading...</p>';

            fetch('/actions/astronaut/ai/list', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                }
            })
            .then(response => {
                console.log('Conversation list response status:', response.status);
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Conversation list data:', data);
                if (data.success && data.conversations) {
                    self.renderConversationList(data.conversations, container);
                } else {
                    container.innerHTML = '<p style="padding: 12px; color: #64748b;">No conversations yet</p>';
                    container.removeAttribute('data-loading');
                }
            })
            .catch(error => {
                console.error('Failed to load conversations:', error);
                container.innerHTML = '<p style="padding: 12px; color: #cf1124;">Failed to load conversations</p>';
                container.removeAttribute('data-loading');
            });
        },

        getRelativeTime: function(date) {
            const now = new Date();
            const diffMs = now - date;
            const diffSecs = Math.floor(diffMs / 1000);
            const diffMins = Math.floor(diffSecs / 60);
            const diffHours = Math.floor(diffMins / 60);
            const diffDays = Math.floor(diffHours / 24);

            if (diffSecs < 30) {
                return 'just now';
            } else if (diffSecs < 60) {
                return 'a few moments ago';
            } else if (diffMins < 2) {
                return 'a minute ago';
            } else if (diffMins < 60) {
                return diffMins + ' minutes ago';
            } else if (diffHours < 2) {
                return 'an hour ago';
            } else if (diffHours < 24) {
                return diffHours + ' hours ago';
            } else if (diffDays === 1) {
                return 'yesterday';
            } else if (diffDays < 7) {
                return diffDays + ' days ago';
            } else if (diffDays < 14) {
                return 'a week ago';
            } else if (diffDays < 30) {
                const weeks = Math.floor(diffDays / 7);
                return weeks + ' weeks ago';
            } else if (diffDays < 60) {
                return 'a month ago';
            } else {
                const months = Math.floor(diffDays / 30);
                return months + ' months ago';
            }
        },

        renderConversationList: function(conversations, container) {
            if (!conversations || conversations.length === 0) {
                container.innerHTML = '<p style="padding: 12px; color: #64748b;">No conversations yet</p>';
                return;
            }

            const self = this;
            let html = '<div style="display: flex; flex-direction: column; gap: 4px;">';

            conversations.forEach(conv => {
                const isActive = conv.threadId === self.currentThreadId;

                // Parse the lastMessageAt date (format: "2025-01-15 12:34:56" in UTC)
                // Add 'Z' to indicate UTC time, then browser will convert to local timezone
                const date = conv.lastMessageAt ? new Date(conv.lastMessageAt.replace(' ', 'T') + 'Z') : new Date();
                const dateStr = self.getRelativeTime(date);

                const title = conv.title || 'New Conversation';
                const messageCount = conv.messageCount || 0;

                // Build style with conditional active state
                const buttonStyle = `
                    display: block;
                    width: 100%;
                    text-align: left;
                    padding: 10px 12px;
                    border: 1px solid ${isActive ? '#0369a1' : 'var(--hairline-color, #e3e5e8)'};
                    border-radius: 4px;
                    background: ${isActive ? '#e0f2fe' : '#fff'};
                    cursor: pointer;
                    transition: all 0.15s ease;
                    font-size: 13px;
                    margin-bottom: 6px;
                `;

                html += `
                    <button
                        class="launcher-ai-conversation-item"
                        data-thread-id="${conv.threadId}"
                        style="${buttonStyle}"
                    >
                        <div style="font-weight: 500; margin-bottom: 4px; color: var(--text-color, #3f4f5f);">${title}</div>
                        <div style="font-size: 11px; color: var(--medium-text-color, #606d7b); display: flex; justify-content: space-between;">
                            <span>${dateStr}</span>
                            <span>${messageCount} ${messageCount === 1 ? 'message' : 'messages'}</span>
                        </div>
                    </button>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
            container.removeAttribute('data-loading');

            // Bind click handlers
            container.querySelectorAll('.launcher-ai-conversation-item').forEach(btn => {
                btn.addEventListener('click', function() {
                    const threadId = this.getAttribute('data-thread-id');
                    self.switchConversation(threadId);
                });
            });
        },

        switchConversation: function(threadId) {
            const self = this;

            // Clear current messages
            this.messagesContainer.innerHTML = '<div class="launcher-ai-welcome"><h3>Astronaut</h3></div>';
            this.currentThreadId = threadId;

            // Load conversation history
            fetch(`/actions/astronaut/ai/history?threadId=${threadId}`, {
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

            // Create a new conversation via API
            fetch('/actions/astronaut/ai/new', {
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
                    // Set the new thread as current
                    self.currentThreadId = data.conversation.threadId;

                    // Clear messages and show welcome
                    self.messagesContainer.innerHTML = `
                        <div class="launcher-ai-welcome">
                            <h3>Astronaut</h3>
                            <p>How can I help you today?</p>
                        </div>
                    `;

                    // Reload conversation list to show new conversation
                    self.loadConversationList();

                    // Focus input
                    self.focusInput();
                } else {
                    console.error('Failed to create new conversation:', data.error);
                    self.showError('Failed to create new conversation');
                }
            })
            .catch(error => {
                console.error('Failed to create new conversation:', error);
                self.showError('Failed to create new conversation');
            });
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
        console.log('[ASTRONAUT] DOMContentLoaded - Setting up drawer monitoring');

        // Track if we've already loaded conversations to prevent loops
        let conversationsLoaded = false;

        // Use MutationObserver to detect when conversation list appears
        const observeConversationContainer = function() {
            const container = document.getElementById('launcher-ai-drawer-conversations');
            if (container && container.hasAttribute('data-loading') && !conversationsLoaded) {
                console.log('[ASTRONAUT] Conversation container found with data-loading, loading conversations...');
                conversationsLoaded = true; // Prevent multiple loads
                container.removeAttribute('data-loading'); // Remove attribute immediately

                if (window.LauncherAI && window.LauncherAI.isInitialized) {
                    window.LauncherAI.loadConversationList();
                }
            }
        };

        // Set up a MutationObserver to watch for the drawer content being added
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length) {
                    observeConversationContainer();
                }
            });
        });

        // Start observing the document body for changes
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        // Also check immediately in case it's already there
        setTimeout(observeConversationContainer, 500);

        // Reset the flag when drawer closes so it can load again next time
        setInterval(function() {
            const drawer = document.querySelector('.launcher-drawer');
            if (drawer && !drawer.classList.contains('launcher-drawer-open')) {
                conversationsLoaded = false;
            }
        }, 1000);

        // Keep old click handler for other buttons
        document.addEventListener('click', function(e) {

            // Handle new chat button in drawer
            if (e.target.closest('#launcher-ai-new-chat-action')) {
                if (window.LauncherAI && window.LauncherAI.isInitialized) {
                    window.LauncherAI.handleNewChat();
                }
            }

            // Handle drawer tab switching
            if (e.target.classList.contains('launcher-ai-drawer-tab')) {
                const tabName = e.target.getAttribute('data-tab');

                // Update tab buttons
                document.querySelectorAll('.launcher-ai-drawer-tab').forEach(tab => {
                    tab.classList.remove('active');
                });
                e.target.classList.add('active');

                // Update panels
                document.querySelectorAll('.launcher-ai-drawer-panel').forEach(panel => {
                    panel.classList.remove('active');
                });
                const targetPanel = document.getElementById('launcher-ai-drawer-' + tabName + '-panel');
                if (targetPanel) {
                    targetPanel.classList.add('active');
                }
            }
        });
    });
})();
