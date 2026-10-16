<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSH Client</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <script>
        // SSH URI provided by the controller
        const sshUri = "<?php echo $sshUri; ?>";
        
        // Attempt to open the sshUri
        try {
            window.location.href = sshUri;
            // Attempt to close the window after opening the URI
            // window.close();
        } catch (error) {
            // Handle failure to open the URI (likely due to missing extension)
            Swal.fire({
                title: 'Secure Shell Extension Not Found',
                text: 'The SSH connection could not be opened. Please ensure the Secure Shell extension is installed.',
                icon: 'error',
                showCancelButton: true,
                confirmButtonText: 'Install Now',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.open('https://chrome.google.com/webstore/detail/secure-shell/iodihamcpbpeioajjeobimgagajmlibd', '_blank');
                } else {
                    window.history.back();
                }
                // Attempt to close the window after handling the error
                // window.close();
            });
        }
    </script>
</body>
</html>
