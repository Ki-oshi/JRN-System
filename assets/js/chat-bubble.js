const chatBubble = document.getElementById('chat-bubble');
const chatPopup = document.getElementById('chat-popup');
const chatMessages = document.getElementById('chat-messages');
const chatIcon = document.getElementById('chat-icon');
const closeIcon = document.getElementById('close-icon');

chatBubble.addEventListener('click', () => {
    chatPopup.classList.toggle('active');
    chatBubble.classList.toggle('active');
    
    // Toggle icons
    if (chatPopup.classList.contains('active')) {
        chatIcon.style.display = 'none';
        closeIcon.style.display = 'block';
        if (chatMessages.children.length === 0) startConversation();
    } else {
        chatIcon.style.display = 'block';
        closeIcon.style.display = 'none';
    }
});

function startConversation() {
    showBotMessage(
        "Hi! 👋 Welcome to JRN Business Solutions Co. How can I help you today?",
        [
            "I want to apply",
            "How long will it take?",
            "What documents are needed?",
            "Other services",
            "Contact information"
        ]
    );
}

// Display bot message with typing animation
function showBotMessage(text, options = null, delay = 1200) {
    showTyping(delay, () => {
        const msg = document.createElement('div');
        msg.classList.add('message', 'bot');
        msg.innerHTML = linkify(text);
        chatMessages.appendChild(msg);
        scrollToBottom();

        // Add service button if applicable
        if (text.includes("To apply") || text.includes("Services page")) {
            setTimeout(() => {
                const container = document.createElement('div');
                container.classList.add('message', 'bot', 'options');
                
                const serviceBtn = document.createElement('button');
                serviceBtn.classList.add('chat-option-btn', 'service-btn');
                serviceBtn.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline; vertical-align: middle; margin-right: 6px;">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <line x1="10" y1="14" x2="21" y2="3"></line>
                    </svg>
                    Go to Services Page
                `;
                serviceBtn.addEventListener('click', () => {
                    window.open("services.php", "_blank");
                });
                container.appendChild(serviceBtn);
                chatMessages.appendChild(container);
                scrollToBottom();
            }, 300);
        }

        if (options) showOptions(options);
    });
}

// Display options
function showOptions(options) {
    const optionsContainer = document.createElement('div');
    optionsContainer.classList.add('message', 'bot', 'options');

    options.forEach(option => {
        const btn = document.createElement('button');
        btn.classList.add('chat-option-btn');
        btn.innerText = option;
        btn.addEventListener('click', () => handleOption(option));
        optionsContainer.appendChild(btn);
    });

    chatMessages.appendChild(optionsContainer);
    scrollToBottom();
}

// Handle option clicks
function handleOption(option) {
    addUserMessage(option);

    switch(option) {
        case "I want to apply":
            showBotMessage(
                `Great! 🎯 To apply for any of our services, please visit our <strong>Services page</strong>.<br><br>
                Each service has detailed instructions including:<br>
                ✅ Required documents<br>
                ✅ Application forms<br>
                ✅ Submission process<br><br>
                After submission, you'll receive a <strong>unique QR code</strong> for tracking your application status.`,
                ["How long will it take?", "What documents are needed?", "End chat"]
            );
            break;

        case "How long will it take?":
            showBotMessage(
                `⏱️ <strong>Processing times vary by service:</strong><br><br>
                <strong>Business Registration:</strong><br>
                • DTI: 1-3 working days<br>
                • SEC: 5-7 working days<br>
                • Mayor's Permit: 3-5 working days<br>
                • BIR: 2-4 working days<br><br>
                <strong>Business Processing:</strong><br>
                • Renewal: 2-4 working days<br>
                • Closure: 3-5 working days<br>
                • Amendment: 2-4 working days<br>
                • BIR Open Cases: 5-10 working days<br><br>
                <em>Note: Processing times may vary depending on government agencies.</em>`,
                ["What documents are needed?", "I want to apply", "End chat"]
            );
            break;

        case "What documents are needed?":
            showBotMessage(
                `📄 <strong>Required Documents:</strong><br><br>
                <strong>For BIR Registration:</strong><br>
                • DTI/SEC Certificate<br>
                • Valid ID<br>
                • Property Title or Lease Contract<br>
                • Special Power of Attorney (if applicable)<br>
                • Required BIR Forms<br><br>
                <strong>For Business Permit:</strong><br>
                • DTI/SEC Certificate<br>
                • Valid ID<br>
                • Property documents<br>
                • Business photos & sketch<br><br>
                For complete lists, visit our Services page.`,
                ["I want to apply", "How long will it take?", "End chat"]
            );
            break;

        case "Other services":
            showBotMessage(
                `💼 <strong>Additional Services:</strong><br><br>
                📊 Bookkeeping<br>
                🤝 Retainership (ongoing support)<br>
                📋 BIR Tax Filing & Compliance<br>
                💰 Annual Income Tax Preparation<br>
                👥 Payroll Management<br>
                📈 Business Consultation<br>
                💡 Tax Advisory Services<br><br>
                Visit our Services page for full details!`,
                ["I want to apply", "Contact information", "End chat"]
            );
            break;

        case "Contact information":
            showBotMessage(
                `📞 <strong>Get in Touch:</strong><br><br>
                📧 Email: 
jrndocumentation@gmail.com<br>
                📱 Phone: (02) 1234-5678<br>
                📍 Address: Manila, Philippines<br><br>
                <strong>Business Hours:</strong><br>
                Monday - Friday: 9:00 AM - 6:00 PM<br>
                Saturday: 9:00 AM - 1:00 PM<br><br>
                Feel free to reach out anytime!`,
                ["I want to apply", "End chat"]
            );
            break;

        case "End chat":
            showBotMessage(
                "Thank you for chatting with us! 😊<br><br>If you have more questions, feel free to ask again.",
                ["Ask again"]
            );
            break;

        case "Ask again":
            showTyping(600, () => startConversation());
            break;

        default:
            showBotMessage(
                "Sorry, I didn't understand that. Please select an option from the menu.",
                ["Ask again", "End chat"]
            );
    }
}

// Add user message
function addUserMessage(text) {
    const msg = document.createElement('div');
    msg.classList.add('message', 'user');
    msg.innerText = text;
    chatMessages.appendChild(msg);

    // Remove previous options
    const previousOptions = chatMessages.querySelectorAll('.message.options');
    previousOptions.forEach(opt => opt.remove());

    scrollToBottom();
}

// Typing animation
function showTyping(delay = 1200, callback) {
    const typingIndicator = document.createElement('div');
    typingIndicator.classList.add('typing');
    typingIndicator.innerHTML = `
        <span class="typing-dot"></span>
        <span class="typing-dot"></span>
        <span class="typing-dot"></span>
    `;
    chatMessages.appendChild(typingIndicator);
    scrollToBottom();

    setTimeout(() => {
        typingIndicator.remove();
        callback();
    }, delay);
}

// Convert URLs to links
function linkify(text) {
    return text.replace(
        /(https?:\/\/[^\s]+)/g,
        '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
    );
}

// Smooth scroll to bottom
function scrollToBottom() {
    chatMessages.scrollTo({
        top: chatMessages.scrollHeight,
        behavior: 'smooth'
    });
}
