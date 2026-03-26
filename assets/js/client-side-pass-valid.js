document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("form[action='register_process.php']");
    if (!form) return;

    const password = form.querySelector("input[name='password']");
    const confirmPassword = form.querySelector("input[name='confirm_password']");
    
    if (!password || !confirmPassword) return;

    class PasswordValidator {
        constructor(passwordInput, confirmPasswordInput) {
            this.password = passwordInput;
            this.confirmPassword = confirmPasswordInput;
            this.init();
        }

        init() {
            this.createUI();
            this.attachEvents();
        }

        createUI() {
            // Create main container that goes after confirm password
            const mainContainer = document.createElement('div');
            mainContainer.className = 'password-validation-wrapper';
            
            // Password strength section
            const strengthSection = document.createElement('div');
            strengthSection.className = 'password-strength-section';
            strengthSection.innerHTML = `
                <div class="strength-header">
                    <span class="strength-label">Password Strength:</span>
                    <span class="strength-text"></span>
                </div>
                <div class="strength-bar">
                    <div class="strength-bar-segment" data-level="1"></div>
                    <div class="strength-bar-segment" data-level="2"></div>
                    <div class="strength-bar-segment" data-level="3"></div>
                    <div class="strength-bar-segment" data-level="4"></div>
                </div>
            `;

            // Requirements section
            const requirementsSection = document.createElement('div');
            requirementsSection.className = 'strength-requirements';
            requirementsSection.innerHTML = `
                <div class="requirement-item" data-req="length">
                    <svg class="req-icon-circle" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                    </svg>
                    <svg class="req-icon-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>At least 8 characters</span>
                </div>
                <div class="requirement-item" data-req="case">
                    <svg class="req-icon-circle" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                    </svg>
                    <svg class="req-icon-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>Upper & lowercase letters</span>
                </div>
                <div class="requirement-item" data-req="number">
                    <svg class="req-icon-circle" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                    </svg>
                    <svg class="req-icon-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>At least one number</span>
                </div>
                <div class="requirement-item" data-req="special">
                    <svg class="req-icon-circle" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                    </svg>
                    <svg class="req-icon-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>Special character (!@#$%^&*)</span>
                </div>
            `;

            // Match indicator section
            const matchSection = document.createElement('div');
            matchSection.className = 'password-match-section';
            matchSection.innerHTML = `<div class="match-indicator"></div>`;

            // Assemble everything
            mainContainer.appendChild(strengthSection);
            mainContainer.appendChild(requirementsSection);
            mainContainer.appendChild(matchSection);

            // Find the confirm password's form-group parent
            const confirmGroup = this.confirmPassword.closest('.form-group');
            
            // Insert AFTER the confirm password form-group (not inside it)
            if (confirmGroup && confirmGroup.parentNode) {
                // Remove any existing validation wrapper first
                const existingWrapper = confirmGroup.parentNode.querySelector('.password-validation-wrapper');
                if (existingWrapper) {
                    existingWrapper.remove();
                }
                
                // Insert after the confirm group
                confirmGroup.parentNode.insertBefore(mainContainer, confirmGroup.nextSibling);
            } else {
                // Fallback: insert after confirm password input
                this.confirmPassword.insertAdjacentElement('afterend', mainContainer);
            }

            // Store references
            this.strengthSegments = mainContainer.querySelectorAll('.strength-bar-segment');
            this.strengthText = mainContainer.querySelector('.strength-text');
            this.requirements = mainContainer.querySelector('.strength-requirements');
            this.matchIndicator = mainContainer.querySelector('.match-indicator');
        }

        checkRequirements(value) {
            return {
                length: value.length >= 8,
                case: /[a-z]/.test(value) && /[A-Z]/.test(value),
                number: /\d/.test(value),
                special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(value)
            };
        }

        calculateScore(requirements) {
            return Object.values(requirements).filter(Boolean).length;
        }

        getStrengthLevel(score) {
            const levels = {
                0: { text: '', color: '' },
                1: { text: 'Weak', color: '#ef4444' },
                2: { text: 'Fair', color: '#f97316' },
                3: { text: 'Good', color: '#3b82f6' },
                4: { text: 'Strong', color: '#10b981' }
            };
            return levels[score];
        }

        updateStrengthDisplay() {
            const value = this.password.value;

            if (value.length === 0) {
                this.strengthSegments.forEach(seg => seg.classList.remove('active'));
                this.strengthText.textContent = '';
                this.requirements.style.display = 'none';
                return;
            }

            const requirements = this.checkRequirements(value);
            const score = this.calculateScore(requirements);
            const level = this.getStrengthLevel(score);

            // Update segments
            this.strengthSegments.forEach((segment, index) => {
                if (index < score) {
                    segment.classList.add('active');
                    segment.style.background = level.color;
                } else {
                    segment.classList.remove('active');
                }
            });

            // Update text
            this.strengthText.textContent = level.text;
            this.strengthText.style.color = level.color;

            // Show requirements
            this.requirements.style.display = 'grid';

            // Update requirement items
            Object.entries(requirements).forEach(([key, met]) => {
                const item = this.requirements.querySelector(`[data-req="${key}"]`);
                if (item) {
                    if (met) {
                        item.classList.add('met');
                    } else {
                        item.classList.remove('met');
                    }
                }
            });
        }

        updateMatchDisplay() {
            const passwordValue = this.password.value;
            const confirmValue = this.confirmPassword.value;

            if (confirmValue.length === 0) {
                this.matchIndicator.className = 'match-indicator';
                this.matchIndicator.innerHTML = '';
                return;
            }

            if (passwordValue === confirmValue && passwordValue.length > 0) {
                this.matchIndicator.className = 'match-indicator match-success';
                this.matchIndicator.innerHTML = `
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <span>Passwords match perfectly!</span>
                `;
            } else {
                this.matchIndicator.className = 'match-indicator match-error';
                this.matchIndicator.innerHTML = `
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                    <span>Passwords do not match</span>
                `;
            }
        }

        attachEvents() {
            this.password.addEventListener('input', () => {
                this.updateStrengthDisplay();
                this.updateMatchDisplay();
            });

            this.confirmPassword.addEventListener('input', () => {
                this.updateMatchDisplay();
            });
        }

        validate() {
            const requirements = this.checkRequirements(this.password.value);
            const score = this.calculateScore(requirements);
            const passwordsMatch = this.password.value === this.confirmPassword.value;

            if (score < 2) {
                this.strengthText.textContent = 'Too weak - please strengthen';
                this.strengthText.style.color = '#ef4444';
                this.password.focus();
                return false;
            }

            if (!passwordsMatch) {
                this.matchIndicator.className = 'match-indicator match-error shake';
                setTimeout(() => this.matchIndicator.classList.remove('shake'), 500);
                this.confirmPassword.focus();
                return false;
            }

            return true;
        }
    }

    const validator = new PasswordValidator(password, confirmPassword);

    form.addEventListener('submit', (e) => {
        if (!validator.validate()) {
            e.preventDefault();
            return false;
        }
    });
});
