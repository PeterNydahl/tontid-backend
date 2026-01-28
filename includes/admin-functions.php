<?php 

class TonTidAdminFunctions{
    static function calculateBookingGridPositionAndDuration($booking){
        $booking_start_datetime = new DateTime($booking->booking_start);
        $booking_start_unix_timestamp = $booking_start_datetime->getTimestamp();
        $booking_start_date = $booking_start_datetime->format('Y-m-d');
        $booking_start_time = $booking_start_datetime->format('H:i');

        $booking_end_datetime = new DateTime($booking->booking_end);
        $booking_start_end_timestamp = $booking_end_datetime->getTimestamp();
        $booking_end_date = $booking_end_datetime->format('Y-m-d');
        $booking_end_time = $booking_end_datetime->format('H:i');

        $weekday_start_datetime = new DateTime($booking_start_date . " " . "8:00:00");
        $weekday_start_unix_timestamp = $weekday_start_datetime->getTimeStamp();

        // Startpunkt för bokning : räkna ut differensen (minuter) mellan veckodagens starttid och bokningens starttid
        // Räkna om timestamp till minuter
        $weekday_start_unix_timestamp_in_minutes = $weekday_start_unix_timestamp / 60;
        $booking_start_unix_timestamp_in_minutes = $booking_start_unix_timestamp / 60;
        $booking_start_minutes_after_from_weekday_start = $booking_start_unix_timestamp_in_minutes - $weekday_start_unix_timestamp_in_minutes; 
        // startpunkt i grid (så att första raden blir 1 och inte 0)
        $booking_start_row = $booking_start_minutes_after_from_weekday_start + 1;
        // Bokningens längd: räkna ut differensen (minuter) mellan bokningens start - och sluttid
        $booking_duration = (($booking_end_datetime->getTimestamp() - $booking_start_datetime->getTimestamp())/60);

        $html = "<div 
                    class='booking'
                    style='grid-row:{$booking_start_row}/span {$booking_duration}'>
                    {$booking->lesson} <br>
                    {$booking_start_time}-{$booking_end_time} <br>
                    {$booking_start_date} <br>
                </div>";

        return $html;
    }
    
}

