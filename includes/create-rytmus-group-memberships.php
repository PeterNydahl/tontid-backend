<?php

if(!defined('ABSPATH')) exit;

function create_rytmus_group_membership_for_everyone(){
    //hämta grupp id (dvs id för term)
    $term_result = get_term_by(
        'slug',
        'rytmus',
        'tontid_group'
    );

    //skapa term om den inte finns
    if(!$term_result){
        $term_result = wp_insert_term('Rytmus', 'tontid_group', [
            'slug' => 'rytmus'
        ]); 
        if(!is_wp_error($term_result)){
            $rytmus_group_id = $term_result['term_id'];    
        } else {
            error_log("Fel när termen rytmus skulle skapas: " . $term_result->get_error_message());
            return;
        }
    } else {
        $rytmus_group_id = $term_result->term_id;
    }

    global $wpdb;
    //hämta alla användare
    $user_ids = get_users([
        'fields' => 'ID'
    ]);

    //om det inte finns användare
    if(empty($user_ids)){
        return;
    }
    $table_memberships = $wpdb->prefix . 'tontid_group_membership';
    $membership_slug = "rytmus";
    $membership_name = "Rytmus";
   
    foreach($user_ids as $user_id){
        //kolla om medlemskap finns
        $membership = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT `user_id` FROM $table_memberships
                WHERE `user_id` = %d
                AND `group_slug` = %s
                LIMIT 1",
                $user_id, $membership_slug)
        );
        if(!$membership){
            $wpdb->insert(
                $table_memberships, 
                [
                    'group_id' => $rytmus_group_id,
                    'user_id' => $user_id,
                    'group_slug' => $membership_slug,
                    'group_name' => $membership_name
                ], 
                [
                    '%d',
                    '%d',
                    '%s',
                    '%s'
                ]
            );
            error_log("Användare med id $user_id är nu medlem i gruppen Rytmus.");
        }

    }
}