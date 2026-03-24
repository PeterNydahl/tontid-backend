<?php

function display_test_environment(){
    echo "<p>Testmiljön is up and running!</p>";
    
    $enArray = array(11,22,33,44,55);
    echo count($enArray)-3 . "<br>";
    // echo $enArray[count($enArray)-4];

    // foreach($enArray as $x){
    //     echo "<p>$x</p>";
    // }

}

