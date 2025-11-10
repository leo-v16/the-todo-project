function handleCredentialResponse(response) {
    $.ajax({
        url: 'google-auth.php',
        type: 'POST',
        data: { credential: response.credential },
        success: function (response) {
            var data = JSON.parse(response);
            if (data.success) {
                window.location.href = '../home/home.php';
            } else {
                alert('Authentication failed: ' + data.error);
            }
        },
        error: function () {
            alert('An error occurred during authentication.');
        }
    });
}