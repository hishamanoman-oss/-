$(document).ready(function() {
    $('#login-form').submit(function(e) {
        e.preventDefault();
        var email = $('#email').val();
        var pass  = $('#password').val();

        $.ajax({
            url: 'login_process.php',
            type: 'POST',
            data: { email: email, password: pass },
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
