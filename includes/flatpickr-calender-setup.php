<?php

function tontid_enqueue_flatpickr( $hook ) {
    // Visa bara på adminsidan för hantera bokningar
    // if ( strpos( $hook, 'tontid-handle-bookings' ) === false ) {
    //     return;
    // }

    // Ladda Flatpickr CSS
    wp_enqueue_style(
        'flatpickr-css',
        'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css'
    );

    // Ladda Flatpickr JavaScript
    wp_enqueue_script(
        'flatpickr-js',
        'https://cdn.jsdelivr.net/npm/flatpickr',
        array(), null, true
    );

    // Ladda svensk översättning (så att dagar och månader blir på svenska)
    wp_enqueue_script(
        'flatpickr-sv',
        'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/sv.js',
        array( 'flatpickr-js' ), null, true
    );

    // Initiera Flatpickr med inställningar och lägga till en custom  "Välj tid"-knapp
    wp_add_inline_script( 'flatpickr-sv', "
    document.addEventListener('DOMContentLoaded', function() {
        const pickers = [];
        let startInput = null;
        let endInput = null;
        let endPicker = null;

        document.querySelectorAll('.tontid-flatpickr-time').forEach(function(elem, index) {
            const picker = flatpickr(elem, {
                enableTime: true,
                dateFormat: 'Y-m-d H:i',
                time_24hr: true,
                defaultHour: 08,     // <--- Ändra till önskad timme (t.ex. 08)
                defaultMinute: 00,   // <--- Ändra till önskad minut
                minTime: '08:00',
                maxTime: '20:00',
                locale: 'sv',
                appendTo: document.body,
                onOpen: function(selectedDates, dateStr, instance) {
                    // Lägg inte till knappen flera gånger
                    if (!instance.calendarContainer.querySelector('.flatpickr-select-btn')) {
                        const selectButton = document.createElement('button');
                        selectButton.textContent = 'Välj tid';
                        selectButton.classList.add('flatpickr-select-btn');
                        instance.calendarContainer.appendChild(selectButton);

                        selectButton.addEventListener('click', function() {
                            instance.close();
                        });
                    }
                }
            });

            pickers.push(picker);

            if (index === 0) startInput = elem;
            if (index === 1) endInput = elem;
        });

        if (startInput && endInput) {
            const startPicker = pickers[0];
            endPicker = pickers[1];

            startPicker.set('onChange', function(selectedDates, dateStr, instance) {
                if (selectedDates.length > 0) {
                    // Skapa ett nytt datumobjekt baserat på valt startdatum
                    const endDate = new Date(selectedDates[0].getTime());
                    
                    // Sätt tiden till exakt 20:00:00
                    endDate.setHours(20);
                    endDate.setMinutes(0);
                    endDate.setSeconds(0);

                    // Uppdatera sluttid-pickern
                    endPicker.setDate(endDate, true);
                }
            });
        }
    });
", true );




    // Lägg till CSS för knappen och input-fältet etc
    wp_add_inline_style( 'flatpickr-css', "
    
    /* Inputfältets styling */
    .tontid-flatpickr-time {
        background-color: white!important;
        padding: 6px 12px;
        border: 1px solid #8c8f94;
        border-radius: 5px;
        height: 30px;
    }
    
    .flatpickr-select-btn {
        background-color: #0073aa; /* WordPress-blå */
        color: white;
        border: none;
        padding: 8px 16px;
        margin-top: 10px;
        margin-bottom: 10px;
        cursor: pointer;
        font-size: 14px;
        border-radius: 5px;
        text-align: center;
        width: 90%; /* Fullbredd */
    }

    .flatpickr-select-btn:hover {
        background-color: #005f7f; /* Mörkare blå vid hover */
    }

");
}