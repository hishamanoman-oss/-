$(document).ready(function() {
    function showError(msg) {
        if (typeof window.showToast === 'function') {
            window.showToast(msg, 'error');
        } else {
            console.error('Login error:', msg);
        }
    }

    $('#login-form').submit(function(e) {
        e.preventDefault();
        var formData = $(this).serialize();

        $.ajax({
            url: 'login_process.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.trim() === "success") {
                    window.location.href = "index.php";
                } else {
                    showError(response);
                }
            },
            error: function(jqXHR, textStatus) {
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
