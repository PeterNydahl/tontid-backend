<?php
/* 
********************************************************************************************
                                      RYTMUS ADMIN                                            
******************************************************************************************** 
*/

// add_action('admin_init', function(){
//     $user = wp_get_current_user();
//     if(in_array('rytmus_admin', $user->roles)){
//         //redirecta till annan sida än default (index.php)
//         global $pagenow;
//         $url = 'admin.php?page=tontid-show-schedule';
//         if($pagenow !== 'admin.php' && (!isset($_GET['page']) || $_GET['page'] !== 'tontid_show_schedule')){
//             wp_redirect(admin_url($url));
//             exit;
//         }
        
//     }
// });

add_action('admin_menu', function(){
    if(in_array('rytmus_admin', wp_get_current_user()->roles)){
        //tar bort menyn "adminpanel"
        remove_menu_page('index.php');
        //tar bort menyn "profil"
        remove_menu_page('profile.php');     
    }
}, 999); //så att callbacken körs efter allt annat laddats in i admin menyn

add_action('admin_bar_menu', function($wp_admin_bar){
    if(in_array('rytmus_admin', wp_get_current_user()->roles)){
        //ta bort WP loggan uppe till vänster
        $wp_admin_bar->remove_node('wp-logo'); 
        $wp_admin_bar->remove_node('site-name');
    }       
}, 999); //annars körs hooken innan WP lägger till loggan

// Ta bort "Tack för att du skapar med WordPress" längst ner
add_filter('admin_footer_text', function() {
    if(in_array('rytmus_admin', wp_get_current_user()->roles)){
        return '©️Syncoria Studios 2025'; // tom sträng = ingen texten. Default är "Tack för att du skapar med WordPress"
    }
});
