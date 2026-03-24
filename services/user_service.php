<?php

if(!defined('ABSPATH')) exit;

//testfunktion för att kolla att denna fil hittas av den anropande filen
function helloUserService(){
    return "Hello from user_service.php!";
}

//hämta användar id via email
function getUserIdByEmail($user_email){
    global $wpdb;
    $user_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT `ID`
            FROM $wpdb->users
            WHERE `user_email` = %s", 
            $user_email)
    );
    return $user_id;
}

//hämta användarens membership-sluggar
function getMembershipSlugs($user_id){
    global $wpdb;
    $table_memberships = $wpdb->prefix . 'tontid_group_membership';
    $membership_slugs = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT `group_slug` FROM $table_memberships
            WHERE `user_id` = %d", 
            $user_id)
    );
    return $membership_slugs;
}
