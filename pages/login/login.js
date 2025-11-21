function handleCredentialResponse(response) {

    $.ajax({
        url: 'google-callback.php',
        type: 'POST',
        data: { credential: response.credential },
        success: function (response) {

            if (response.success) {
                window.location.href = '../home/home.php';
            } else {
                alert('Authentication failed: ' + response.error);
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
    
            alert('An error occurred during authentication.');
        }
    });
}