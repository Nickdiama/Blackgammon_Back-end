<?php

function handle_start_dice($method, $input) {
    if ($method !== 'POST') {
        header('HTTP/1.1 405 Method Not Allowed');
        echo json_encode(['error' => 'POST only']);
        return;
    }

    global $mysqli; 

    // 1. Έλεγχος token
    if (empty($input['token'])) {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['error' => 'Missing token']);
        return;
    }

    // 2. Βρες παίκτη από token
    $stmt = $mysqli->prepare(
        "SELECT piece_color FROM players WHERE token = ?"
    );
    $stmt->bind_param("s", $input['token']);
    $stmt->execute();
    $stmt->bind_result($color);
    $stmt->fetch();
    $stmt->close();

    if (!$color) {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['error' => 'Invalid token']);
        return;
    }

    // 3. Έλεγχος game status
    $res = $mysqli->query(
        "SELECT status, start_dice_w, start_dice_b 
         FROM game_status"
    );
    $gs = $res->fetch_assoc();

    if ($gs['status'] !== 'initialized') {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'Game not in initial roll phase']);
        return;
    }

    // 4. Έλεγχος αν έχει ήδη ρίξει
    if ($color === 'W' && $gs['start_dice_w'] !== null) {
        echo json_encode(['error' => 'You already rolled']);
        return;
    }
    if ($color === 'B' && $gs['start_dice_b'] !== null) {
        echo json_encode(['error' => 'You already rolled']);
        return;
    }

    // 5. Ρίξε ζάρι
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

    // 6. Ξαναδιάβασε για να δούμε αν έριξαν και οι δύο
    $res = $mysqli->query(
        "SELECT start_dice_w, start_dice_b FROM game_status LIMIT 1"
    );
    $gs = $res->fetch_assoc();

    // 7. Αν έχουν ρίξει και οι δύο
    if ($gs['start_dice_w'] !== null && $gs['start_dice_b'] !== null) {

        if ($gs['start_dice_w'] == $gs['start_dice_b']) {
            // Ισοπαλία → ξαναρίχνουν
            $mysqli->query(
                "UPDATE game_status 
                 SET start_dice_w = NULL,
                     start_dice_b = NULL"
            );

            echo json_encode([
                'you_rolled'   => $roll,
                'result'       => 'tie',
                'message'      => 'Tie, roll again'
            ]);
            return;
        }

        // Βρες ποιος ξεκινά
        $starter = ($gs['start_dice_w'] > $gs['start_dice_b']) ? 'W' : 'B';

        $mysqli->query(
            "UPDATE game_status 
             SET p_turn = '$starter',
                 status = 'started'"
        );

        echo json_encode([
            'you_rolled' => $roll,
            'starter'   => $starter,
            'status'    => 'started'
        ]);
        return;
    }

    // 8. Αν περιμένουμε τον άλλο παίκτη
    echo json_encode([
        'you_rolled' => $roll,
        'waiting_for_other' => true
    ]);
}

?>