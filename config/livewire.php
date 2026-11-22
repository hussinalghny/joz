<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Temporary file uploads
    |--------------------------------------------------------------------------
    |
    | Livewire saves a file here before the form validates it, with the extension
    | it was sent with. Its default is the site's default disk, which on this site
    | is the public one, so uploads must be sent to the private disk instead and
    | anything that is not an image or a web video refused at the door. Videos
    | (page and landing videos, admin only) need the larger size and time limits.
    |
    */

    'temporary_file_upload' => [
        'disk' => 'local',
        'rules' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif,mp4,webm', 'max:262144'],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => ['png', 'gif', 'jpg', 'jpeg', 'webp'],
        'max_upload_time' => 30,
        'cleanup' => true,
    ],

];
