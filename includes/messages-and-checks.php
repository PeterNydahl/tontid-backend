<?php

class TonTidUtils {
    /*************************************************************************************
                                        AVISERINGSSÄNDARE
    *************************************************************************************/
    /* INFORMATIONSMEDDELANDEN 🦉
    ---------------------------------------------------- */
    public static function show_notice_no_bookings_found(){
        echo '<div class="notice notice-info is-dismissible"><p>Sorry! Inga bokningar hittades för denna sal!</p></div>';
     }
    /* VARNINGSMEDDELANDEN ⚠️☢️⚡
    ---------------------------------------------------- */
    public static function show_notice_missing_id(){
        echo '<div class="notice notice-warning is-dismissible"><p><strong>Varning:</strong> Det verkar som att du glömde fylla i ett ID!</p></div>';
    }
    
    /* FELMEDDELANDEN 🤦‍♂️🤬
    ---------------------------------------------------- */
    
    public static function show_notice_error(){
        echo '<div class="notice notice-error is-dismissible"><p><strong>Fel:</strong> Något gick fel.</p></div>';
    }
    public static function show_notice_id_already_exists() {
        echo '<div class="notice notice-error is-dismissible"><p><strong>Fel:</strong> Rums-ID:t du angav finns redan. Välj ett annat ID.</p></div>';
    }
    
    public static function show_notice_room_not_added(){
        echo '<div class="notice notice-error is-dismissible"><p><strong>Fel:</strong> Rummet kunde tyvärr inte läggas till.</p></div>';
    }
    
    public static function show_notice_missing_fields(){
        echo '<div class="notice notice-error is-dismissible"><p><strong>Fel:</strong> Du verkar inte ha fyllt i alla fält! Var vänlig försök igen.</p></div>';
    }

    public static function show_notice_invalid_nonce() {
    echo '<div class="notice notice-error is-dismissible"><p><strong>Fel:</strong> Ogiltig säkerhetskontroll (nonce). Vänligen försök igen.</p></div>';
    }

    
    public static function show_notice_database_error() {
        echo '<div class="notice notice-error is-dismissible"><p><strong>Fel:</strong> Ett fel uppstod vid lagring i databasen.</p></div>';
    }
    
    
    // felemeddelanden specifikt för bokningar (och schemablockeringar)
    public static function show_notice_booking_conflict() {
        echo '<div class="notice notice-error is-dismissible"><p><strong>Fel:</strong> Ooops! Den valda tiden krockar med en redan bokad tid i det här rummet!😬</p></div>';
    }

    public static function show_notice_invalid_date() {
        echo '<div class="notice notice-error is-dismissible"><p><strong>Fel:</strong> Ogiltigt datumformat.</p></div>';
    }
    public static function show_notice_end_before_start(){
        echo '<div class="notice notice-error is-dismissible"><p><strong>Fel:</strong> Sluttid får inte vara före starttid! Var vänlig försök igen.</p></div>';
    }

    public static function show_notice_booking_in_past() {
        echo '<div class="notice notice-error is-dismissible"><p><strong>Fel:</strong> Du kan inte boka bakåt i tiden.</p></div>';
    }

    public static function show_notice_booking_too_long() {
        echo '<div class="notice notice-error is-dismissible"><p><strong>Fel:</strong> En bokning får vara max två timmar lång.</p></div>';
    }

    public static function show_notice_booking_on_weekend() {
        echo '<div class="notice notice-error is-dismissible"><p><strong>Fel:</strong> Bokningar på lördagar eller söndagar är inte tillåtna.</p></div>';
    }

    public static function show_notice_booking_outside_hours() {
        echo '<div class="notice notice-error is-dismissible"><p><strong>Fel:</strong> Bokningar måste ske mellan 08:00 och 20:00.</p></div>';
    }

    public static function show_notice_delete_booking_failed() {
        echo '<div class="notice notice-error is-dismissible"><p><strong>Fel:</strong> Något gick fel och bokningen kunde inte genomföras.😫</p></div>';
    }

    public static function show_notice_schedule_block_failed() {
        echo '<div class="notice notice-error is-dismissible"><p><strong>Fel:</strong> Något gick fel och schemablockeringen kunde inte genomföras.😫</p></div>';
    }
    

    /* MEDDELANDEN OM LYCKA & FRAMGÅNG 🖖
    ---------------------------------------------------- */
    public static function show_notice_schedule_block_was_deleted(){
        echo '<div class="notice notice-success is-dismissible"><p><strong> Schemablockeringen raderades utan problem!👌</strong></p></div>'; 
    }
    
    public static function show_notice_booking_was_deleted(){
        echo '<div class="notice notice-success is-dismissible"><p><strong> Bokningen raderades utan problem!👌</strong></p></div>';
    }

    public static function show_notice_room_was_added(){
        echo '<div class="notice notice-success is-dismissible"><p><strong> Ett nytt rum har lagts till!</strong></p></div>';
    }

    public static function show_notice_room_was_deleted(){
        echo '<div class="notice notice-success is-dismissible"><p><strong> Rummet har tagits bort!</strong></p></div>';
    }

    public static function show_notice_booking_added(){
        echo '<div class="notice notice-success is-dismissible"><p><strong> Din bokning har lagts till!</strong></p></div>';
    }

    public static function show_notice_blocking_added(){
        echo '<div class="notice notice-success is-dismissible"><p><strong> Din schemablockering har lagts till!</strong></p></div>';
    }
 
}


