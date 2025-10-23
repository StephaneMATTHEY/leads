<?php
/**
 * Fichier: services/class-email-sender.php  
 * Service d'envoi d'emails avec support HTML
 */

if (!defined('ABSPATH')) {
    exit;
}

class LC_Email_Sender {
    
    public static function send($to, $subject, $body, $options = array()) {
        
        $from_name = get_option('lc_from_name', get_bloginfo('name'));
        $from_email = get_option('lc_from_email', get_option('admin_email'));
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        );
        
        $result = wp_mail($to, $subject, $body, $headers);
        
        do_action('lc_email_sent', $to, $subject, $result, $options);
        
        return $result;
    }
}
