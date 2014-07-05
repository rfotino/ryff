<?php

class Riff {
    public $id;
    public $title;
    public $duration;
    public $link;
    
    protected function __construct($id, $title, $duration, $link) {
        $this->id = (int)$id;
        $this->title = $title;
        $this->duration = $duration;
        $this->link = $link;
    }
    
    public static function get_by_post_id($post_id) {
        global $db;
        
        $riff_query = "SELECT * FROM `riffs` WHERE `post_id`=".$db->real_escape_string($post_id);
        $riff_results = $db->query($riff_query);
        if ($riff_results && $riff_results->num_rows && $riff_row = $riff_results->fetch_assoc()) {
            $riff_id = $riff_row['riff_id'];
            $path = RIFF_ABSOLUTE_PATH."/$riff_id.m4a";
            if (file_exists($path)) {
                return new Riff($riff_row['riff_id'], $riff_row['title'], 
                                $riff_row['duration'], SITE_ROOT."/riffs/$riff_id.m4a");
            }
        }
        
        return false;
    }
}
