$(document).ready(function() {
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
                    alert(response);
                }
            },
            error: function(jqXHR, textStatus) {
                var responseMsg = jqXHR.responseText ? jqXHR.responseText.trim() : "";
                if (jqXHR.status === 403) {
                    alert(responseMsg ? responseMsg + "\nيرجى تحديث الصفحة والمحاولة مرة أخرى." : "انتهت صلاحية الجلسة، يرجى تحديث الصفحة والمحاولة مرة أخرى.");
                } else if (responseMsg) {
                    alert(responseMsg);
                } else if (jqXHR.status === 0) {
                    alert("حصل خطأ في الاتصال بالسيرفر، يرجى التأكد من تشغيل الخادم والاتصال بالشبكة.");
                } else {
                    alert("حدث خطأ في السيرفر (رمز الخطأ: " + jqXHR.status + ")");
                }
            }
        });
    });
});
