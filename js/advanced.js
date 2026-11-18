// ملف JavaScript المتقدم للوظائف التفاعلية

// إعدادات التطبيق
const APP_CONFIG = {
    version: '1.0.0',
    name: 'لوحة التحكم المتقدمة',
    author: 'هشام نعمان',
    autoSaveInterval: 30000, // 30 ثانية
    animationDuration: 300,
    maxFileSize: 5 * 1024 * 1024, // 5MB
    supportedFormats: ['json', 'csv', 'txt']
};

// متغيرات عامة
let autoSaveTimer = null;
let currentTheme = 'dark';
let isOnline = navigator.onLine;

// فئة إدارة الإشعارات
class NotificationManager {
    constructor() {
        this.notifications = [];
        this.container = this.createContainer();
    }

    createContainer() {
        const container = document.createElement('div');
        container.id = 'notification-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        `;
        document.body.appendChild(container);
        return container;
    }

    show(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        const id = Date.now();
        
        notification.className = `alert alert-${type} alert-dismissible fade show`;
        notification.style.cssText = `
            margin-bottom: 10px;
            animation: slideInRight 0.3s ease-out;
        `;
        
        notification.innerHTML = `
            <i class="fas fa-${this.getIcon(type)} me-2"></i>
            ${message}
            <button type="button" class="btn-close" onclick="notificationManager.hide(${id})"></button>
        `;
        
        notification.dataset.id = id;
        this.container.appendChild(notification);
        this.notifications.push({ id, element: notification });
        
        if (duration > 0) {
            setTimeout(() => this.hide(id), duration);
        }
        
        return id;
    }

    hide(id) {
        const notification = this.notifications.find(n => n.id === id);
        if (notification) {
            notification.element.style.animation = 'slideOutRight 0.3s ease-in';
            setTimeout(() => {
                if (notification.element.parentNode) {
                    notification.element.remove();
                }
                this.notifications = this.notifications.filter(n => n.id !== id);
            }, 300);
        }
    }

    getIcon(type) {
        const icons = {
            success: 'check-circle',
            danger: 'exclamation-triangle',
            warning: 'exclamation-circle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    clear() {
        this.notifications.forEach(n => this.hide(n.id));
    }
}

// فئة إدارة البيانات المحلية
class LocalStorageManager {
    constructor() {
        this.prefix = 'dashboard_';
    }

    set(key, value) {
        try {
            const data = {
                value: value,
                timestamp: Date.now(),
                version: APP_CONFIG.version
            };
            localStorage.setItem(this.prefix + key, JSON.stringify(data));
            return true;
        } catch (error) {
            console.error('خطأ في حفظ البيانات:', error);
            return false;
        }
    }

    get(key, defaultValue = null) {
        try {
            const item = localStorage.getItem(this.prefix + key);
            if (!item) return defaultValue;
            
            const data = JSON.parse(item);
            return data.value;
        } catch (error) {
            console.error('خطأ في قراءة البيانات:', error);
            return defaultValue;
        }
    }

    remove(key) {
        localStorage.removeItem(this.prefix + key);
    }

    clear() {
        const keys = Object.keys(localStorage).filter(key => key.startsWith(this.prefix));
        keys.forEach(key => localStorage.removeItem(key));
    }

    getSize() {
        let total = 0;
        const keys = Object.keys(localStorage).filter(key => key.startsWith(this.prefix));
        keys.forEach(key => {
            total += localStorage.getItem(key).length;
        });
        return total;
    }

    export() {
        const data = {};
        const keys = Object.keys(localStorage).filter(key => key.startsWith(this.prefix));
        keys.forEach(key => {
            const cleanKey = key.replace(this.prefix, '');
            data[cleanKey] = this.get(cleanKey);
        });
        return data;
    }

    import(data) {
        Object.keys(data).forEach(key => {
            this.set(key, data[key]);
        });
    }
}

// فئة إدارة الرسوم المتحركة
class AnimationManager {
    static fadeIn(element, duration = APP_CONFIG.animationDuration) {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        element.style.transition = `all ${duration}ms ease-out`;
        
        setTimeout(() => {
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }, 10);
    }

    static fadeOut(element, duration = APP_CONFIG.animationDuration) {
        element.style.transition = `all ${duration}ms ease-in`;
        element.style.opacity = '0';
        element.style.transform = 'translateY(-20px)';
        
        return new Promise(resolve => {
            setTimeout(() => {
                element.style.display = 'none';
                resolve();
            }, duration);
        });
    }

    static slideIn(element, direction = 'right', duration = APP_CONFIG.animationDuration) {
        const transforms = {
            right: 'translateX(100%)',
            left: 'translateX(-100%)',
            up: 'translateY(-100%)',
            down: 'translateY(100%)'
        };
        
        element.style.transform = transforms[direction];
        element.style.transition = `transform ${duration}ms ease-out`;
        
        setTimeout(() => {
            element.style.transform = 'translateX(0) translateY(0)';
        }, 10);
    }

    static pulse(element, intensity = 1.1, duration = 200) {
        element.style.transition = `transform ${duration}ms ease-out`;
        element.style.transform = `scale(${intensity})`;
        
        setTimeout(() => {
            element.style.transform = 'scale(1)';
        }, duration);
    }

    static shake(element, intensity = 10, duration = 500) {
        const keyframes = [
            { transform: 'translateX(0)' },
            { transform: `translateX(-${intensity}px)` },
            { transform: `translateX(${intensity}px)` },
            { transform: `translateX(-${intensity}px)` },
            { transform: 'translateX(0)' }
        ];
        
        element.animate(keyframes, {
            duration: duration,
            easing: 'ease-in-out'
        });
    }
}

// فئة إدارة التحقق من صحة البيانات
class ValidationManager {
    static validateEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }

    static validatePhone(phone) {
        const regex = /^[0-9+\-\s()]+$/;
        return regex.test(phone) && phone.length >= 10;
    }

    static validateRequired(value) {
        return value && value.toString().trim().length > 0;
    }

    static validateNumber(value, min = null, max = null) {
        const num = parseFloat(value);
        if (isNaN(num)) return false;
        if (min !== null && num < min) return false;
        if (max !== null && num > max) return false;
        return true;
    }

    static validateLength(value, min = 0, max = Infinity) {
        const length = value ? value.toString().length : 0;
        return length >= min && length <= max;
    }

    static validateForm(formElement) {
        const errors = [];
        const inputs = formElement.querySelectorAll('input[required], textarea[required], select[required]');
        
        inputs.forEach(input => {
            const value = input.value.trim();
            const label = input.previousElementSibling?.textContent || input.name || 'الحقل';
            
            if (!this.validateRequired(value)) {
                errors.push(`${label} مطلوب`);
                this.highlightError(input);
            } else {
                this.clearError(input);
                
                // تحقق إضافي حسب نوع الحقل
                if (input.type === 'email' && !this.validateEmail(value)) {
                    errors.push(`${label} غير صحيح`);
                    this.highlightError(input);
                } else if (input.type === 'tel' && !this.validatePhone(value)) {
                    errors.push(`${label} غير صحيح`);
                    this.highlightError(input);
                } else if (input.type === 'number') {
                    const min = input.min ? parseFloat(input.min) : null;
                    const max = input.max ? parseFloat(input.max) : null;
                    if (!this.validateNumber(value, min, max)) {
                        errors.push(`${label} خارج النطاق المسموح`);
                        this.highlightError(input);
                    }
                }
            }
        });
        
        return {
            isValid: errors.length === 0,
            errors: errors
        };
    }

    static highlightError(input) {
        input.classList.add('is-invalid');
        input.style.borderColor = '#dc3545';
        AnimationManager.shake(input);
    }

    static clearError(input) {
        input.classList.remove('is-invalid');
        input.style.borderColor = '';
    }
}

// فئة إدارة البحث المتقدم
class SearchManager {
    constructor() {
        this.searchHistory = storageManager.get('searchHistory', []);
    }

    search(data, query, fields = []) {
        if (!query || query.trim().length === 0) {
            return data;
        }

        const searchTerm = query.toLowerCase().trim();
        this.addToHistory(searchTerm);

        return data.filter(item => {
            if (fields.length === 0) {
                // البحث في جميع الحقول
                return Object.values(item).some(value => 
                    value && value.toString().toLowerCase().includes(searchTerm)
                );
            } else {
                // البحث في حقول محددة
                return fields.some(field => 
                    item[field] && item[field].toString().toLowerCase().includes(searchTerm)
                );
            }
        });
    }

    addToHistory(term) {
        if (!this.searchHistory.includes(term)) {
            this.searchHistory.unshift(term);
            this.searchHistory = this.searchHistory.slice(0, 10); // الاحتفاظ بآخر 10 عمليات بحث
            storageManager.set('searchHistory', this.searchHistory);
        }
    }

    getHistory() {
        return this.searchHistory;
    }

    clearHistory() {
        this.searchHistory = [];
        storageManager.remove('searchHistory');
    }

    createSearchSuggestions(inputElement) {
        const suggestionsContainer = document.createElement('div');
        suggestionsContainer.className = 'search-suggestions';
        suggestionsContainer.style.cssText = `
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #2d3748;
            border: 1px solid #4a5568;
            border-radius: 8px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        `;

        inputElement.parentNode.style.position = 'relative';
        inputElement.parentNode.appendChild(suggestionsContainer);

        inputElement.addEventListener('focus', () => {
            this.showSuggestions(suggestionsContainer, inputElement);
        });

        inputElement.addEventListener('blur', () => {
            setTimeout(() => {
                suggestionsContainer.style.display = 'none';
            }, 200);
        });

        return suggestionsContainer;
    }

    showSuggestions(container, inputElement) {
        const history = this.getHistory();
        if (history.length === 0) return;

        container.innerHTML = '';
        history.forEach(term => {
            const suggestion = document.createElement('div');
            suggestion.className = 'search-suggestion-item';
            suggestion.style.cssText = `
                padding: 10px;
                cursor: pointer;
                border-bottom: 1px solid #4a5568;
                transition: background-color 0.2s;
            `;
            suggestion.textContent = term;

            suggestion.addEventListener('mouseenter', () => {
                suggestion.style.backgroundColor = '#4a5568';
            });

            suggestion.addEventListener('mouseleave', () => {
                suggestion.style.backgroundColor = '';
            });

            suggestion.addEventListener('click', () => {
                inputElement.value = term;
                container.style.display = 'none';
                inputElement.dispatchEvent(new Event('input'));
            });

            container.appendChild(suggestion);
        });

        container.style.display = 'block';
    }
}

// فئة إدارة التصدير والاستيراد
class DataManager {
    static exportToJSON(data, filename) {
        const jsonData = JSON.stringify(data, null, 2);
        this.downloadFile(jsonData, filename, 'application/json');
    }

    static exportToCSV(data, filename) {
        if (!data || data.length === 0) {
            notificationManager.show('لا توجد بيانات للتصدير', 'warning');
            return;
        }

        const headers = Object.keys(data[0]);
        const csvContent = [
            headers.join(','),
            ...data.map(row => 
                headers.map(header => `"${row[header] || ''}"`).join(',')
            )
        ].join('\n');

        this.downloadFile(csvContent, filename, 'text/csv');
    }

    static downloadFile(content, filename, mimeType) {
        const blob = new Blob([content], { type: mimeType });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        
        link.href = url;
        link.download = filename;
        link.style.display = 'none';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        URL.revokeObjectURL(url);
        notificationManager.show('تم تصدير البيانات بنجاح!', 'success');
    }

    static async importFromFile(file) {
        return new Promise((resolve, reject) => {
            if (!file) {
                reject(new Error('لم يتم اختيار ملف'));
                return;
            }

            if (file.size > APP_CONFIG.maxFileSize) {
                reject(new Error('حجم الملف كبير جداً'));
                return;
            }

            const reader = new FileReader();
            
            reader.onload = (e) => {
                try {
                    const content = e.target.result;
                    const extension = file.name.split('.').pop().toLowerCase();
                    
                    let data;
                    if (extension === 'json') {
                        data = JSON.parse(content);
                    } else if (extension === 'csv') {
                        data = this.parseCSV(content);
                    } else {
                        throw new Error('تنسيق الملف غير مدعوم');
                    }
                    
                    resolve(data);
                } catch (error) {
                    reject(new Error('خطأ في قراءة الملف: ' + error.message));
                }
            };
            
            reader.onerror = () => {
                reject(new Error('خطأ في قراءة الملف'));
            };
            
            reader.readAsText(file);
        });
    }

    static parseCSV(content) {
        const lines = content.split('\n').filter(line => line.trim());
        if (lines.length < 2) throw new Error('ملف CSV فارغ أو غير صحيح');
        
        const headers = lines[0].split(',').map(h => h.replace(/"/g, '').trim());
        const data = [];
        
        for (let i = 1; i < lines.length; i++) {
            const values = lines[i].split(',').map(v => v.replace(/"/g, '').trim());
            const row = {};
            
            headers.forEach((header, index) => {
                row[header] = values[index] || '';
            });
            
            data.push(row);
        }
        
        return data;
    }
}

// فئة إدارة الحفظ التلقائي
class AutoSaveManager {
    constructor() {
        this.isEnabled = true;
        this.interval = APP_CONFIG.autoSaveInterval;
        this.timer = null;
        this.lastSave = Date.now();
    }

    start() {
        if (!this.isEnabled) return;
        
        this.timer = setInterval(() => {
            this.save();
        }, this.interval);
    }

    stop() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    }

    save() {
        try {
            // حفظ البيانات الحالية
            storageManager.set('clients', clients);
            storageManager.set('products', products);
            storageManager.set('posts', posts);
            
            this.lastSave = Date.now();
            
            // إظهار مؤشر الحفظ
            this.showSaveIndicator();
            
        } catch (error) {
            console.error('خطأ في الحفظ التلقائي:', error);
        }
    }

    showSaveIndicator() {
        let indicator = document.getElementById('auto-save-indicator');
        
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'auto-save-indicator';
            indicator.style.cssText = `
                position: fixed;
                bottom: 20px;
                left: 20px;
                background: #38a169;
                color: white;
                padding: 8px 16px;
                border-radius: 20px;
                font-size: 12px;
                z-index: 9998;
                opacity: 0;
                transition: opacity 0.3s;
            `;
            indicator.innerHTML = '<i class="fas fa-save me-1"></i>تم الحفظ تلقائياً';
            document.body.appendChild(indicator);
        }
        
        indicator.style.opacity = '1';
        
        setTimeout(() => {
            indicator.style.opacity = '0';
        }, 2000);
    }

    getLastSaveTime() {
        return new Date(this.lastSave).toLocaleString('ar-SA');
    }
}

// إنشاء مثيلات الفئات
const notificationManager = new NotificationManager();
const storageManager = new LocalStorageManager();
const searchManager = new SearchManager();
const autoSaveManager = new AutoSaveManager();

// وظائف مساعدة عامة
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

function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

function formatDate(date, locale = 'ar-SA') {
    return new Date(date).toLocaleDateString(locale);
}

function formatCurrency(amount, currency = 'USD') {
    return new Intl.NumberFormat('ar-SA', {
        style: 'currency',
        currency: currency
    }).format(amount);
}

function generateId() {
    return Date.now() + Math.random().toString(36).substr(2, 9);
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        notificationManager.show('تم نسخ النص', 'success', 2000);
    }).catch(() => {
        notificationManager.show('فشل في نسخ النص', 'danger');
    });
}

// مراقبة حالة الاتصال
window.addEventListener('online', () => {
    isOnline = true;
    notificationManager.show('تم استعادة الاتصال بالإنترنت', 'success');
});

window.addEventListener('offline', () => {
    isOnline = false;
    notificationManager.show('انقطع الاتصال بالإنترنت', 'warning');
});

// إضافة أنماط CSS للرسوم المتحركة
const animationStyles = document.createElement('style');
animationStyles.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    .fade-in-up {
        animation: fadeInUp 0.5s ease-out;
    }
    
    @keyframes fadeInUp {
        from { transform: translateY(30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    
    .loading-spinner {
        border: 3px solid #f3f3f3;
        border-top: 3px solid #4299e1;
        border-radius: 50%;
        width: 30px;
        height: 30px;
        animation: spin 1s linear infinite;
        margin: 0 auto;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(animationStyles);

// بدء الحفظ التلقائي عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', () => {
    autoSaveManager.start();
    
    // إضافة مؤشر حالة الاتصال
    const statusIndicator = document.createElement('div');
    statusIndicator.id = 'connection-status';
    statusIndicator.style.cssText = `
        position: fixed;
        top: 10px;
        left: 10px;
        padding: 5px 10px;
        border-radius: 15px;
        font-size: 12px;
        z-index: 9999;
        transition: all 0.3s;
    `;
    
    function updateConnectionStatus() {
        if (isOnline) {
            statusIndicator.style.background = '#38a169';
            statusIndicator.style.color = 'white';
            statusIndicator.innerHTML = '<i class="fas fa-wifi me-1"></i>متصل';
        } else {
            statusIndicator.style.background = '#e53e3e';
            statusIndicator.style.color = 'white';
            statusIndicator.innerHTML = '<i class="fas fa-wifi-slash me-1"></i>غير متصل';
        }
    }
    
    updateConnectionStatus();
    document.body.appendChild(statusIndicator);
    
    // تحديث حالة الاتصال كل 30 ثانية
    setInterval(updateConnectionStatus, 30000);
});

// تصدير الوظائف للاستخدام العام
window.notificationManager = notificationManager;
window.storageManager = storageManager;
window.searchManager = searchManager;
window.autoSaveManager = autoSaveManager;
window.AnimationManager = AnimationManager;
window.ValidationManager = ValidationManager;
window.DataManager = DataManager;
