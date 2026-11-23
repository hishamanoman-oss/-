$(document).ready(function() {
    // 1. زر إظهار / إخفاء كلمة المرور
    $('#togglePassword').on('click', function() {
        var passwordInput = $('#password');
        var icon = $(this).find('i');
        
        if (passwordInput.attr('type') === 'password') {
            passwordInput.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            passwordInput.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // 2. دالة إظهار رسائل الخطأ والتنبيهات
    function showError(msg) {
        if (typeof window.showToast === 'function') {
            window.showToast(msg, 'error');
        } else {
            console.error('Login error:', msg);
        }
    }

    // 3. معالجة إرسال النموذج عبر AJAX
    $('#login-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitBtn = form.find('#submitBtn');
        var originalBtnHtml = submitBtn.html();
        var formData = form.serialize();

        // تفعيل حالة التحميل
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> جاري التحقق...');

        $.ajax({
            url: 'login_process.php',
            type: 'POST',
            data: formData,
            dataType: 'text',
            success: function(response) {
                var cleanResponse = response ? response.trim() : "";
                if (cleanResponse === "success") {
                    if (typeof window.showToast === 'function') {
                        window.showToast("تم التحقق بنجاح! جاري الانتقال...", 'success');
                    }
                    setTimeout(function() {
                        window.location.href = "index.php";
                    }, 500);
                } else {
                    submitBtn.prop('disabled', false).html(originalBtnHtml);
                    showError(cleanResponse || "بيانات الدخول غير صحيحة");
                }
            },
            error: function(jqXHR, textStatus) {
                submitBtn.prop('disabled', false).html(originalBtnHtml);
                var responseMsg = jqXHR.responseText ? jqXHR.responseText.trim() : "";
                
                if (jqXHR.status === 403) {
                    showError(responseMsg ? responseMsg + " - يرجى تحديث الصفحة والمحاولة مرة أخرى." : "انتهت صلاحية الجلسة، يرجى تحديث الصفحة والمحاولة مرة أخرى.");
                } else if (responseMsg) {
                    showError(responseMsg);
                } else if (jqXHR.status === 0) {
                    showError("حصل خطأ في الاتصال بالسيرفر، يرجى التأكد من تشغيل الخادم والاتصال بالشبكة.");
                } else {
                    showError("حدث خطأ في السيرفر (رمز الخطأ: " + jqXHR.status + ")");
                }
            }
        });
    });
});
