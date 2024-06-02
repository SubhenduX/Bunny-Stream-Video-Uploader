<?php
/*
Plugin Name: Bunny Stream Video Uploader
Description: A plugin to upload videos to Bunny Stream via direct file upload or URL.
Version: 1.0
Author: SubhenduX
*/

// Register the menu item for the plugin settings page
function bsvu_add_admin_menu() {
    add_menu_page('Bunny Stream Video Uploader', 'Bunny Stream Video Uploader', 'manage_options', 'bsvu', 'bsvu_options_page');
}
add_action('admin_menu', 'bsvu_add_admin_menu');

// Register the settings for the plugin
function bsvu_settings_init() {
    register_setting('bsvu_options', 'bsvu_settings');
    
    add_settings_section(
        'bsvu_section', 
        __('Bunny Stream API Settings', 'wordpress'), 
        'bsvu_settings_section_callback', 
        'bsvu_options'
    );

    add_settings_field( 
        'bsvu_api_key', 
        __('API Key', 'wordpress'), 
        'bsvu_api_key_render', 
        'bsvu_options', 
        'bsvu_section' 
    );

    add_settings_field( 
        'bsvu_library_id', 
        __('Library ID', 'wordpress'), 
        'bsvu_library_id_render', 
        'bsvu_options', 
        'bsvu_section' 
    );
}
add_action('admin_init', 'bsvu_settings_init');

// Render the API Key input
function bsvu_api_key_render() {
    $options = get_option('bsvu_settings');
    ?>
    <input type='text' name='bsvu_settings[bsvu_api_key]' value='<?php echo $options['bsvu_api_key']; ?>'>
    <?php
}

// Render the Library ID input
function bsvu_library_id_render() {
    $options = get_option('bsvu_settings');
    ?>
    <input type='text' name='bsvu_settings[bsvu_library_id]' value='<?php echo $options['bsvu_library_id']; ?>'>
    <?php
}

// Callback for settings section
function bsvu_settings_section_callback() {
    echo __('Enter your Bunny Stream API credentials here.', 'wordpress');
}

// Create the settings page
function bsvu_options_page() {
    ?>
    <form action='options.php' method='post'>
        <h1>Bunny Stream Video Uploader</h1>
        <?php
        settings_fields('bsvu_options');
        do_settings_sections('bsvu_options');
        submit_button();
        ?>
    </form>
    <h2>Upload Video</h2>
    <form action="" method="post" enctype="multipart/form-data">
        <label for="video_title">Video Title:</label>
        <input type="text" id="video_title" name="video_title"><br><br>

        <label for="video_file">Upload Video File:</label>
        <input type="file" id="video_file" name="video_file"><br><br>

        <label for="video_url">Or Enter Video URL:</label>
        <input type="url" id="video_url" name="video_url"><br><br>

        <input type="submit" name="submit_video" value="Upload Video">
    </form>
    <?php
    if (isset($_POST['submit_video'])) {
        bsvu_handle_video_upload();
    }
}

// Handle the video upload
function bsvu_handle_video_upload() {
    $options = get_option('bsvu_settings');
    $apiKey = $options['bsvu_api_key'];
    $libraryId = $options['bsvu_library_id'];
    $videoTitle = sanitize_text_field($_POST['video_title']);
    $videoFile = $_FILES['video_file'];
    $videoUrl = esc_url_raw($_POST['video_url']);

    if (!$apiKey || !$libraryId) {
        echo '<div class="error"><p>Please set your API key and Library ID in the plugin settings.</p></div>';
        return;
    }

    function createVideo($apiKey, $libraryId, $videoTitle) {
        $url = "https://video.bunnycdn.com/library/$libraryId/videos";
        $data = json_encode(['title' => $videoTitle]);
        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'AccessKey' => $apiKey,
            ],
            'body' => $data,
        ]);
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true)['guid'];
    }

    function uploadVideoFile($apiKey, $libraryId, $videoId, $videoFile) {
        $url = "https://video.bunnycdn.com/library/$libraryId/videos/$videoId";
        $fileData = file_get_contents($videoFile['tmp_name']);
        $response = wp_remote_request($url, [
            'method' => 'PUT',
            'headers' => [
                'AccessKey' => $apiKey,
                'Content-Type' => 'application/octet-stream',
            ],
            'body' => $fileData,
        ]);
        return wp_remote_retrieve_body($response);
    }

    function fetchVideoFromUrl($apiKey, $libraryId, $videoUrl) {
        $url = "https://video.bunnycdn.com/library/$libraryId/videos/fetch";
        $data = json_encode(['url' => $videoUrl]);
        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'AccessKey' => $apiKey,
            ],
            'body' => $data,
        ]);
        return wp_remote_retrieve_body($response);
    }

    $videoId = createVideo($apiKey, $libraryId, $videoTitle);

    if (!empty($videoFile['tmp_name'])) {
        $uploadResponse = uploadVideoFile($apiKey, $libraryId, $videoId, $videoFile);
        echo '<div class="updated"><p>Video uploaded from file: ' . esc_html($uploadResponse) . '</p></div>';
    } elseif (!empty($videoUrl)) {
        $fetchResponse = fetchVideoFromUrl($apiKey, $libraryId, $videoUrl);
        echo '<div class="updated"><p>Video fetched from URL: ' . esc_html($fetchResponse) . '</p></div>';
    } else {
        echo '<div class="error"><p>Please upload a video file or provide a video URL.</p></div>';
    }
}
?>
