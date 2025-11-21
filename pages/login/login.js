function handleCredentialResponse(response) {
    console.log("Credential response received:", response);
    $.ajax({
        url: 'google-callback.php',
        type: 'POST',
        data: { credential: response.credential },
        success: function (response) {
            console.log("AJAX success:", response);
            try {
                var data = JSON.parse(response);
                if (data.success) {
                    window.location.href = '../home/home.php';
                } else {
                    alert('Authentication failed: ' + data.error);
                }
            } catch (e) {
                console.error("Error parsing JSON response:", e);
                alert('An error occurred during authentication.');
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log("AJAX error:", textStatus, errorThrown);
            alert('An error occurred during authentication.');
        }
    });
}