/**
 * Kosovo Government Portal - Interactive Functionality
 * Professional JavaScript for Notary Reservation System
 * ====================================================
 */

(function() {
    'use strict';

    // DOM Content Loaded
    document.addEventListener('DOMContentLoaded', function() {
        initializeApplication();
    });

    /**
     * Initialize Application
     */
    function initializeApplication() {
        console.log('🇽🇰 Kosovo Government Portal - Noteria Elektronike Initialized');

        // Initialize Components
        initializeLanguageSelector();
        initializeTinkyPayment();
        initializeFormValidation();
        initializeResponsiveFeatures();
        initializeAccessibilityFeatures();

        // Initialize Performance Optimizations
        initializeLazyLoading();
        initializeFormPersistence();
    }

    /**
     * Language Selector Functionality
     */
    function initializeLanguageSelector() {
        const langSelect = document.getElementById('lang');
        if (!langSelect) return;

        langSelect.addEventListener('change', function() {
            // Add loading state
            const originalText = this.options[this.selectedIndex].text;
            this.disabled = true;

            // Show loading indicator
            showNotification('Duke ndryshuar gjuhën...', 'info');

            // Submit form after short delay for UX
            setTimeout(() => {
                this.closest('form').submit();
            }, 300);
        });
    }

    /**
     * Tinky Payment System
     */
    function initializeTinkyPayment() {
        const bankSelect = document.getElementById('emri_bankes');
        const tinkyForm = document.getElementById('form-tinky-dropdown');
        const mainBankFormBtn = document.getElementById('btn-standard-pay');
        const tinkySubmit = document.getElementById('tinky-submit');

        if (!bankSelect || !tinkyForm) return;

        // Toggle Tinky Form
        bankSelect.addEventListener('change', function() {
            const isTinky = this.value === 'Tinky';

            // Animate form toggle
            if (isTinky) {
                fadeIn(tinkyForm);
                if (mainBankFormBtn) fadeOut(mainBankFormBtn);

                // Auto-focus first input
                setTimeout(() => {
                    const firstInput = tinkyForm.querySelector('input[name="payer_name"]');
                    if (firstInput) {
                        firstInput.focus();
                        firstInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }, 300);
            } else {
                fadeOut(tinkyForm);
                if (mainBankFormBtn) fadeIn(mainBankFormBtn);
            }
        });

        // Tinky Form Submission
        if (tinkySubmit) {
            tinkySubmit.addEventListener('click', handleTinkySubmission);
        }

        // Real-time IBAN validation
        const ibanInput = tinkyForm?.querySelector('input[name="payer_iban"]');
        if (ibanInput) {
            ibanInput.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
                validateIBAN(this);
            });
        }
    }

    /**
     * Handle Tinky Payment Submission
     */
    function handleTinkySubmission(e) {
        e.preventDefault();

        const tinkyForm = document.getElementById('form-tinky-dropdown');
        if (!tinkyForm) return;

        // Get form data
        const formData = {
            payerName: tinkyForm.querySelector('input[name="payer_name"]')?.value.trim(),
            payerIban: tinkyForm.querySelector('input[name="payer_iban"]')?.value.trim(),
            amount: tinkyForm.querySelector('input[name="amount"]')?.value,
            csrfToken: tinkyForm.querySelector('input[name="csrf_token"]')?.value,
            reservationId: tinkyForm.querySelector('input[name="reservation_id"]')?.value
        };

        // Validate form
        if (!validateTinkyForm(formData)) {
            return;
        }

        // Show loading state
        const originalText = this.innerHTML;
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Duke procesuar...';

        // Prepare data for submission
        const fd = new FormData();
        fd.append('payment_method', 'tinky');
        fd.append('csrf_token', formData.csrfToken);
        fd.append('reservation_id', formData.reservationId);
        fd.append('payer_name', formData.payerName);
        fd.append('payer_iban', formData.payerIban);
        fd.append('amount', formData.amount);

        // Submit payment
        fetch('tinky_payment.php', {
            method: 'POST',
            body: fd,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            // Reset button
            this.disabled = false;
            this.innerHTML = originalText;

            // Handle response
            if (data.success) {
                showNotification(data.message || 'Pagesa u krye me sukses!', 'success');

                // Redirect if specified
                if (data.redirect) {
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 2000);
                }

                // Update payment status if on page
                updatePaymentStatus(data);
            } else {
                showNotification(data.message || 'Pagesa dështoi.', 'error');
            }
        })
        .catch(error => {
            // Reset button
            this.disabled = false;
            this.innerHTML = originalText;

            console.error('Tinky Payment Error:', error);
            showNotification('Gabim në komunikim me serverin. Ju lutemi provoni përsëri.', 'error');
        });
    }

    /**
     * Validate Tinky Form
     */
    function validateTinkyForm(data) {
        // Name validation
        if (!data.payerName || data.payerName.length < 2) {
            showNotification('Ju lutemi shkruani emrin dhe mbiemrin e plotë.', 'error');
            return false;
        }

        // IBAN validation
        if (!data.payerIban || !isValidIBAN(data.payerIban)) {
            showNotification('Ju lutemi shkruani një IBAN të vlefshëm për Kosovën (XK...).', 'error');
            return false;
        }

        // Amount validation
        const amount = parseFloat(data.amount);
        if (!amount || amount < 10 || amount > 10000) {
            showNotification('Shuma duhet të jetë ndërmjet 10€ dhe 10,000€.', 'error');
            return false;
        }

        return true;
    }

    /**
     * IBAN Validation for Kosovo
     */
    function isValidIBAN(iban) {
        // Remove spaces and convert to uppercase
        iban = iban.replace(/\s/g, '').toUpperCase();

        // Check Kosovo IBAN format (XK + 18 digits)
        if (!/^XK\d{18}$/.test(iban)) {
            return false;
        }

        // Basic IBAN validation algorithm
        const rearranged = iban.slice(4) + iban.slice(0, 4);
        const numeric = rearranged.split('').map(char => {
            const code = char.charCodeAt(0);
            return code >= 65 && code <= 90 ? (code - 55).toString() : char;
        }).join('');

        let remainder = numeric.slice(0, 9);
        for (let i = 9; i < numeric.length; i += 7) {
            remainder = (remainder + numeric.slice(i, i + 7)) % 97;
        }

        return remainder === 1;
    }

    /**
     * Real-time IBAN validation
     */
    function validateIBAN(input) {
        const iban = input.value.replace(/\s/g, '').toUpperCase();
        const isValid = iban.length === 0 || isValidIBAN(iban);

        input.classList.toggle('is-valid', iban.length > 0 && isValid);
        input.classList.toggle('is-invalid', iban.length > 0 && !isValid);

        // Update visual feedback
        const feedback = input.parentNode.querySelector('.iban-feedback');
        if (!feedback) {
            const fb = document.createElement('div');
            fb.className = 'iban-feedback form-text';
            input.parentNode.appendChild(fb);
        }

        const fb = input.parentNode.querySelector('.iban-feedback');
        if (iban.length > 0) {
            if (isValid) {
                fb.textContent = '✓ IBAN i vlefshëm për Kosovën';
                fb.className = 'iban-feedback form-text text-success';
            } else {
                fb.textContent = '✗ IBAN i pavlefshëm. Duhet të fillojë me XK dhe të ketë 18 shifra.';
                fb.className = 'iban-feedback form-text text-danger';
            }
        } else {
            fb.textContent = '';
        }
    }

    /**
     * Form Validation
     */
    function initializeFormValidation() {
        // Real-time validation for reservation form
        const reservationForm = document.querySelector('form[method="POST"]');
        if (!reservationForm) return;

        const inputs = reservationForm.querySelectorAll('input, select, textarea');

        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });

            input.addEventListener('input', function() {
                if (this.classList.contains('is-invalid')) {
                    validateField(this);
                }
            });
        });

        // Date validation
        const dateInput = document.getElementById('date');
        if (dateInput) {
            dateInput.addEventListener('change', function() {
                validateKosovoBusinessDay(this);
            });
        }

        // Time validation
        const timeInput = document.getElementById('time');
        if (timeInput) {
            timeInput.addEventListener('change', function() {
                validateBusinessHours(this);
            });
        }
    }

    /**
     * Validate Individual Field
     */
    function validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let message = '';

        switch (field.name) {
            case 'service':
                isValid = value !== '';
                message = 'Ju lutemi zgjidhni një shërbim.';
                break;

            case 'date':
                isValid = value !== '' && new Date(value) >= new Date();
                message = 'Ju lutemi zgjidhni një datë të vlefshme në të ardhmen.';
                break;

            case 'time':
                isValid = value !== '';
                message = 'Ju lutemi zgjidhni një orar.';
                break;
        }

        field.classList.toggle('is-valid', isValid && value !== '');
        field.classList.toggle('is-invalid', !isValid && value !== '');

        // Update feedback
        updateFieldFeedback(field, message, isValid);
    }

    /**
     * Validate Kosovo Business Day
     */
    function validateKosovoBusinessDay(dateField) {
        const selectedDate = dateField.value;
        const date = new Date(`${selectedDate}T00:00:00`);
        const kosovoHolidays = [
            '01-01', // Vit i Ri
            '01-02', // Dita e Përzierjes së Kombeve
            '02-17', // Dita e Pavarësisë së Kosovës
            '03-08', // Dita e Grave
            '03-18', // Festa e Fitër Bajramit
            '04-09', // Dita e Përkujtimit të Masakrës së Reçakut
            '05-01', // Dita e Punëtorëve
            '05-09', // Dita e Evropës
            '06-12', // Dita e Çlirimit
            '09-28', // Dita e Kushtetutës
            '12-25', // Krishtlindje
        ];
        const specificHolidayDates = [
            '2026-03-20', // Dita e parë e Fitër Bajramit
        ];

        const dateFormatted = selectedDate.slice(5, 10); // MM-DD format
        const isHoliday = kosovoHolidays.includes(dateFormatted);
        const isSpecificHoliday = specificHolidayDates.includes(selectedDate);
        const isWeekend = date.getDay() === 0 || date.getDay() === 6;

        const isValid = !isHoliday && !isSpecificHoliday && !isWeekend;

        dateField.classList.toggle('is-valid', isValid);
        dateField.classList.toggle('is-invalid', !isValid);

        let message = '';
        if (isSpecificHoliday) {
            message = '20 Mars 2026 është ditë pushimi (Dita e parë e Fitër Bajramit).';
        } else if (isHoliday) {
            message = 'Kjo datë është festë zyrtare në Republikën e Kosovës.';
        } else if (isWeekend) {
            message = 'Zyrat noteriale nuk punojnë në fundjavë.';
        }

        updateFieldFeedback(dateField, message, isValid);
    }

    /**
     * Validate Business Hours
     */
    function validateBusinessHours(timeField) {
        const time = timeField.value;
        const hour = parseInt(time.split(':')[0]);

        const isValid = hour >= 8 && hour <= 16;

        timeField.classList.toggle('is-valid', isValid);
        timeField.classList.toggle('is-invalid', !isValid);

        const message = isValid ? '' : 'Orari i punës është nga 08:00 deri në 16:00.';
        updateFieldFeedback(timeField, message, isValid);
    }

    /**
     * Update Field Feedback
     */
    function updateFieldFeedback(field, message, isValid) {
        let feedback = field.parentNode.querySelector('.field-feedback');

        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'field-feedback form-text mt-1';
            field.parentNode.appendChild(feedback);
        }

        if (message) {
            feedback.textContent = message;
            feedback.className = `field-feedback form-text mt-1 ${isValid ? 'text-success' : 'text-danger'}`;
        } else {
            feedback.textContent = '';
        }
    }

    /**
     * Responsive Features
     */
    function initializeResponsiveFeatures() {
        // Mobile menu toggle
        const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
        const navMenu = document.querySelector('.gov-nav .navbar-collapse');

        if (mobileMenuToggle && navMenu) {
            mobileMenuToggle.addEventListener('click', function() {
                navMenu.classList.toggle('show');
            });
        }

        // Close mobile menu on link click
        const navLinks = navMenu?.querySelectorAll('.nav-link');
        navLinks?.forEach(link => {
            link.addEventListener('click', function() {
                navMenu.classList.remove('show');
            });
        });

        // Table responsiveness
        const tables = document.querySelectorAll('.gov-table');
        tables.forEach(table => {
            makeTableResponsive(table);
        });
    }

    /**
     * Make Table Responsive
     */
    function makeTableResponsive(table) {
        const wrapper = document.createElement('div');
        wrapper.className = 'table-responsive';
        table.parentNode.insertBefore(wrapper, table);
        wrapper.appendChild(table);
    }

    /**
     * Accessibility Features
     */
    function initializeAccessibilityFeatures() {
        // Skip to main content link
        const skipLink = document.createElement('a');
        skipLink.href = '#main-content';
        skipLink.className = 'sr-only sr-only-focusable';
        skipLink.textContent = 'Kalo te përmbajtja kryesore';
        document.body.insertBefore(skipLink, document.body.firstChild);

        // Keyboard navigation for cards
        const cards = document.querySelectorAll('.gov-card');
        cards.forEach(card => {
            card.setAttribute('tabindex', '0');

            card.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    const link = this.querySelector('a');
                    if (link) link.click();
                }
            });
        });

        // High contrast mode detection
        if (window.matchMedia && window.matchMedia('(prefers-contrast: high)').matches) {
            document.body.classList.add('high-contrast');
        }
    }

    /**
     * Lazy Loading
     */
    function initializeLazyLoading() {
        // Lazy load images
        const images = document.querySelectorAll('img[data-src]');
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    observer.unobserve(img);
                }
            });
        });

        images.forEach(img => imageObserver.observe(img));
    }

    /**
     * Form Persistence
     */
    function initializeFormPersistence() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            // Save form data to sessionStorage
            form.addEventListener('input', debounce(function() {
                const formData = new FormData(form);
                const data = {};
                for (let [key, value] of formData.entries()) {
                    data[key] = value;
                }
                sessionStorage.setItem(`form_${form.id || 'default'}`, JSON.stringify(data));
            }, 500));

            // Restore form data
            const savedData = sessionStorage.getItem(`form_${form.id || 'default'}`);
            if (savedData) {
                try {
                    const data = JSON.parse(savedData);
                    Object.keys(data).forEach(key => {
                        const input = form.querySelector(`[name="${key}"]`);
                        if (input) {
                            input.value = data[key];
                        }
                    });
                } catch (e) {
                    console.warn('Could not restore form data:', e);
                }
            }
        });
    }

    /**
     * Update Payment Status
     */
    function updatePaymentStatus(data) {
        const statusElements = document.querySelectorAll('[data-payment-status]');
        statusElements.forEach(element => {
            if (data.payment_status) {
                element.textContent = data.payment_status === 'paid' ? 'Paguar' : 'Në pritje';
                element.className = data.payment_status === 'paid' ? 'badge bg-success' : 'badge bg-warning';
            }
        });
    }

    /**
     * Show Notification
     */
    function showNotification(message, type = 'info') {
        // Remove existing notifications
        const existing = document.querySelectorAll('.gov-notification');
        existing.forEach(el => el.remove());

        // Create notification
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} gov-notification position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);';
        notification.innerHTML = `
            <i class="fas fa-${getNotificationIcon(type)} me-2"></i>
            ${message}
            <button type="button" class="btn-close float-end" onclick="this.parentElement.remove()"></button>
        `;

        document.body.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                fadeOut(notification, () => notification.remove());
            }
        }, 5000);
    }

    /**
     * Get Notification Icon
     */
    function getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'times-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    /**
     * Utility Functions
     */
    function fadeIn(element, duration = 300) {
        element.style.display = 'block';
        element.style.opacity = '0';
        let opacity = 0;
        const increment = 50 / duration;

        const fade = () => {
            opacity += increment;
            element.style.opacity = opacity;

            if (opacity < 1) {
                requestAnimationFrame(fade);
            }
        };

        requestAnimationFrame(fade);
    }

    function fadeOut(element, duration = 300, callback = null) {
        let opacity = 1;
        const decrement = 50 / duration;

        const fade = () => {
            opacity -= decrement;
            element.style.opacity = opacity;

            if (opacity > 0) {
                requestAnimationFrame(fade);
            } else {
                element.style.display = 'none';
                if (callback) callback();
            }
        };

        requestAnimationFrame(fade);
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Export functions for global use
    window.KosovoGovernmentPortal = {
        showNotification,
        validateIBAN,
        fadeIn,
        fadeOut
    };

})();