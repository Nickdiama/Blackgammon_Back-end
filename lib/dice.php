<?php

// Χειρισμός ρίψης ζαριού για την έναρξη του παιχνιδιού
function handle_start_dice($input) {
    global $mysqli; 

    // Έλεγχος token
    if (empty($input['token'])) {
        header('HTTP/1.1 401 Unauthorized');
        print json_encode(['error' => 'Missing token']);
        return;
    }

    // Βρες παίκτη από token
    $stmt = $mysqli->prepare(
        "SELECT piece_color FROM players WHERE token = ?"
    );
    $stmt->bind_param("s", $input['token']);
    $stmt->execute();
    $stmt->bind_result($color);
    $stmt->fetch();
    $stmt->close();

    // Έλεγχος έγκυρου token
    if (!$color) {
        header('HTTP/1.1 401 Unauthorized');
        print json_encode(['error' => 'Invalid token']);
        return;
    }

    // Έλεγχος game status
    $res = $mysqli->query(
        "SELECT status, start_dice_w, start_dice_b 
         FROM game_status"
    );
    $gs = $res->fetch_assoc();

    // Έλεγχος αν έχει ήδη ρίξει
    if ($color === 'W' && $gs['start_dice_w'] !== null) {
        print json_encode(['error' => 'You already rolled']);
        return;
    }
    if ($color === 'B' && $gs['start_dice_b'] !== null) {
        print json_encode(['error' => 'You already rolled']);
        return;
    }

    // Ρίξε ζάρι
    $roll = random_int(1, 6);

    if ($color === 'W') {
        $mysqli->query(
            "UPDATE game_status SET start_dice_w = $roll"
        );
    } else {
        $mysqli->query(
            "UPDATE game_status SET start_dice_b = $roll"
        );
    }

    // Ξαναδιάβασε για να δούμε αν έριξαν και οι δύο
    $res = $mysqli->query(
        "SELECT start_dice_w, start_dice_b FROM game_status LIMIT 1"
    );
    $gs = $res->fetch_assoc();

    // Έλεγχος αν έριξαν και οι δύο
    if ($gs['start_dice_w'] !== null && $gs['start_dice_b'] !== null) {
        // Αν είναι ισοπαλία
        if ($gs['start_dice_w'] == $gs['start_dice_b']) {
            $mysqli->query(
                "UPDATE game_status SET start_dice_w = NULL, start_dice_b = NULL"
            );

            print json_encode([
                'you_rolled'   => $roll,
                'result'       => 'tie',
                'message'      => 'Roll again'
            ]);
            return;
        }

        // Βρες ποιος ξεκινά
        $starter = ($gs['start_dice_w'] > $gs['start_dice_b']) ? 'W' : 'B';
        
        // Ενημέρωσε την κατάσταση παιχνιδιού
        $mysqli->query("UPDATE game_status SET p_turn = '$starter', status = 'started'");

        print json_encode([
            'you_rolled' => $roll,
            'starter'   => $starter,
            'status'    => 'started'
        ]);
        return;
    }

    // Αν περιμένουμε τον άλλο παίκτη να ρίξει
    print json_encode([
        'you_rolled' => $roll,
        'waiting_for_other' => true
    ]);
}

// Ρήψη ζαριού κατά τη διάρκεια του παιχνιδιού
function handle_game_dice() {
    global $mysqli;
    // Έλεγχος game status
    $res = $mysqli->query("SELECT status, p_turn, dice1, dice2 FROM game_status LIMIT 1");
    $gs = $res->fetch_assoc();

    // Έλεγχος αν υπάρχουν ήδη ζάρια
    if ($gs['dice1'] !== null || $gs['dice2'] !== null) {
        header('HTTP/1.1 400 Bad Request');
        print json_encode(['error' => 'Dice already rolled']);
        return;
    }

    // Ρίξε ζάρι
    $d1 = random_int(1, 6);
    $d2 = random_int(1, 6);

    // Αποθήκευσε τα ζάρια
    $stmt = $mysqli->prepare("UPDATE game_status SET dice1 = ?, dice2 = ?");
    $stmt->bind_param("ii", $d1, $d2);
    $stmt->execute();
    $stmt->close();

    // Επιστροφή αποτελέσματος
    print json_encode([
        'dice1' => $d1,
        'dice2' => $d2
    ]);

}

?>