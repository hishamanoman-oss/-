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
            error: function() {
                alert("حصل خطأ في الاتصال بالسيرفر");
            }
        });
    });
});
