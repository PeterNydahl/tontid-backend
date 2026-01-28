<?php

if(!defined('ABSPATH')) {
    wp_die('Du har inte rätt till direktåtkomst av den här filen.');
}

function tontid_create_roles() {
    global $wp_roles;

    //loopa igenom och visa i error loggen alla roller som finns    
    // foreach ($wp_roles->roles as $role_slug => $role_info) {
    //     error_log("Role slug: $role_slug | Name: {$role_info['name']}");
    // }


    //Adding capability to superadmin
    $admin = get_role('administrator');
    $admin->add_cap('tontid_view_menu');
    
    // remove_role('rytmus_admin');

    //Rytmus admin
    if(!get_role('rytmus_admin')){
        add_role( 
            'rytmus_admin', 
            'Rytmus Admin', 
            [
                'read' => true, //standars WP grej, betyder rätt och slätt att man kan logga in på dashboard
                'rytmus_admin_dashboard_ui' => true,
                'tontid_view_menu' => true

            ]
        );
    }
    
    //Lärare
    if(!get_role('larare')){
        add_role(
            'larare',      // internt namn – inga specialtecken
            'Lärare',      // visningsnamn – svenska med å, ä, ö är OK
            [
                'read' => true,
                'edit_posts' => true
            ]
        );
    }
    
    //Elev
    if(!get_role('elev')){
        add_role(
            'elev',
            'Elev',
            [
                'read' => true,
                'edit_posts' => true
            ]
        );
    }

    //Pianoelev 
    if(!get_role('elev_piano')){
        add_role(
            'elev_piano',
            'Pianoelev',[
                'read' => true, 
                'edit_posts' => true
            ]
        );
    }
    
    //Trumelev
    if(!get_role('elev_trummor')){
        add_role(
            'elev_trummor',
            'Trumelev', [
                'read' => true,
                'edit_posts' => true
            ]
        );
    }

    //Elev med studiokörkort
    if(!get_role('elev_studiokorkort')){
        add_role(
            'elev_studiokorkort',         // Kodnamn, används internt
            'Elev med studiokörkort', [  // Synligt namn för användaren
                'read' => true, //Kan logga in & se admin
                'edit_posts' => true //Kan skapa/redigera inlägg
            ]
        );
    }

    //Pianoelev med studiokörkort
    if(!get_role('pianoelev_studiokorkort')){
        add_role(
            'pianoelev_studiokorkort',        
            'Pianoelev med studiokörkort', [  
                'read' => true, 
                'edit_posts' => true 
            ]
        );
    }

    //Trumelev med studiokörkort
    if(!get_role('trumelev_studiokorkort')){
        add_role(
            'trumelev_studiokorkort',        
            'Trumelev med studiokörkort', [  
                'read' => true, 
                'edit_posts' => true 
            ]
        );
    }

    //Mupr åk 1
    if(!get_role('elev_mupr_ak1')){
        add_role(
            'elev_mupr_ak1',
            'Mupr åk 1', [
                'read' => true,
                'edit_posts' => true
            ]
        );
    }

    //Mupr åk 2
    if(!get_role('elev_mupr_ak2')){
        add_role(
            'elev_mupr_ak2',
            'Mupr åk 2', [
                'read' => true,
                'edit_posts' => true
            ]
        );
    }

    //Mupr åk 3
    if(!get_role('elev_mupr_ak3')){
        add_role(
            'elev_mupr_ak3',
            'Mupr åk 3', [
                'read' => true,
                'edit_posts' => true
            ]
        );
    }
}

