<?php
require_once "lib/users.php";
require_once "lib/game.php";

function show_board() {
	header('Content-type: application/json');
	print json_encode(read_board(), JSON_PRETTY_PRINT);
}

function read_board() {
    global $mysqli;
	$sql = 'select * from board';
	$st = $mysqli->prepare($sql);
	$st->execute();
	$res = $st->get_result();
	return($res->fetch_all(MYSQLI_ASSOC));
}

function reset_board($input) {
	global $mysqli;
	$sql = 'call clean_board()';
	$mysqli->query($sql);
	show_board($input);
}

function move_piece($from, $to, $token) {
    global $mysqli;
	//Έλεγχος ύπαρξης token
	if($token==null || $token=='') {
		header("HTTP/1.1 400 Bad Request");
		print json_encode(['errormesg'=>"token is not set."]);
		exit;
	}

	//Έλεγχος ύπαρξης παίκτη με αυτό το token
	$color = current_color($token); //Επιστρέφει το χρώμα που έχει ο παίκτης με το συγκεκριμένο token
	if($color==null ) {
		header("HTTP/1.1 400 Bad Request");
		print json_encode(['errormesg'=>"You are not a player of this game."]);
		exit;
	}

	//Εάν το παιχνίδι δεν βρίσκεται σε κατάσταση started, raise error
	$status = read_status();
	if($status['status']!='started') {
		if($status['status']=='ended') {
			header("HTTP/1.1 400 Bad Request");
			print json_encode([
				'errormesg'=>"Game has ended. To start a new game, please reset the board.",
				'result'=>$status['result'],
				'score'=>$status['score'],
				]);
			exit;
		}
		header("HTTP/1.1 400 Bad Request");
		print json_encode(['errormesg'=>"Game is not in action."]);
		exit;
	}
	
	//Έλεγχος αν είναι η σειρά του παίκτη
	if($status['p_turn']!=$color) {
		header("HTTP/1.1 400 Bad Request");
		print json_encode(['errormesg'=>"It is not your turn."]);
		exit;
	}

	//Έλεγχος αν υπαρχουν διαθέσιμες κινήσεις
	if (!player_has_move($color, $status)) {
		$next = ($color === 'W') ? 'B' : 'W';
        $mysqli->query("UPDATE game_status SET p_turn='$next', double_count=0, dice1=NULL, dice2=NULL, dice3=NULL, dice4=NULL");
		header("HTTP/1.1 200 OK");
		print json_encode(['errormesg'=>"No available moves. Opponent's turn now."]);
		exit;
	}
	
	$column_name = ($color === 'W') ? 'w_count' : 'b_count';
	// Έλεγχος αν υπάρχει κομμάτι του παίκτη στη θέση 'from'
	$st = $mysqli->prepare("SELECT $column_name FROM board WHERE position = ?");
	$st->bind_param("i", $from);
	$st->execute();
	$cnt = $st->get_result()->fetch_row()[0];
	if ($cnt <= 0) {
		header("HTTP/1.1 400 Bad Request");
		print json_encode(['errormesg'=>"No piece of yours at the 'from' position."]);
		exit;
	}

	//Ελεγχος κατεύθυνσης κίνησης
	if ($color == 'W' && $to <= $from) {
		header("HTTP/1.1 400 Bad Request");
		print json_encode(['errormesg'=>"You cannot go backwards."]);
		exit;
	} elseif ($color == 'B' && $to >= $from) {
		header("HTTP/1.1 400 Bad Request"); 
		print json_encode(['errormesg'=>"You cannot go backwards."]);
		exit;
	}

	// Έλεγχος αν η απόσταση είναι σύμφωνη με τις τιμές των ζαριών
	$distance = abs($to - $from);
	if ($distance == 0) {
    	header("HTTP/1.1 400 Bad Request");
    	print json_encode(['errormesg'=>"You cannot stay in the same position."]);
    	exit;
	}elseif ($distance!=$status['dice1'] && $distance!=$status['dice2'] && $distance!=($status['dice1']+$status['dice2']) && 
		($distance!=$status['dice3'] && $distance!=$status['dice4'] && $distance!=($status['dice3']+$status['dice4']))) {
		header("HTTP/1.1 400 Bad Request");
		print json_encode(['errormesg'=>"You can only move by the dice values."]);
		exit;
	}

	$st = $mysqli->prepare("SELECT top_color FROM board WHERE position = ?");
	$st->bind_param("i", $from);
	$st->execute();
	$top_color_from = $st->get_result()->fetch_row()[0];

	$st = $mysqli->prepare("SELECT top_color FROM board WHERE position = ?");
	$st->bind_param("i", $to);
	$st->execute();
	$top_color_to = $st->get_result()->fetch_row()[0];

	// Έλεγχος αν το κομμάτι είναι μπλοκαρισμένο
	if ($color != $top_color_from && $top_color_from != null) {
		header("HTTP/1.1 400 Bad Request");
		print json_encode(['errormesg'=>"Your piece is blocked by opponent piece."]);
		exit;
	}

	// Έλεγχος αν η θέση 'to' είναι κατειλημμένη από δύο ή περισσότερα κομμάτια του αντιπάλου
	if ($color != $top_color_to && $top_color_to != null) {
		header("HTTP/1.1 400 Bad Request");
		print json_encode(['errormesg'=>"The 'to' position is blocked by opponent pieces."]);
		exit;
	}

	// Έλεγχος αν ο παίκτης μπορεί να βγάλει κομμάτια έξω από το ταμπλό
	if ($color == 'W' && $to == 25) {
		$st = $mysqli->prepare("SELECT SUM(w_count) FROM board WHERE position BETWEEN 19 AND 24");
		$st->execute();
		$white_home = (int)$st->get_result()->fetch_row()[0];
		$st = $mysqli->prepare("SELECT w_count FROM board WHERE position = 25");
		$st->execute();
		$white_out = (int)$st->get_result()->fetch_row()[0]; //Αριθμός λευκών κομματιών που έχουν βγει έξω
		if ($white_home <15 && $white_out < 0) {
			header("HTTP/1.1 400 Bad Request");
			print json_encode(['errormesg'=>"You cannot bear off until all your pieces are in your home board. Choose another move."]);
			exit;
		}
	} elseif ($color == 'B' && $to == 0) {
		$st = $mysqli->prepare("SELECT SUM(b_count) FROM board WHERE position BETWEEN 1 AND 6");
		$st->execute();
		$black_home = (int)$st->get_result()->fetch_row()[0];
		$st = $mysqli->prepare("SELECT b_count FROM board WHERE position = 0");
		$st->execute();
		$black_out = (int)$st->get_result()->fetch_row()[0]; //Αριθμός μαύρων κομματιών που έχουν βγει έξω
		if ($black_home <15 && $black_out < 0) {
			header("HTTP/1.1 400 Bad Request");
			print json_encode(['errormesg'=>"You cannot bear off until all your pieces are in your home board. Choose another move."]);
			exit;
		}
	}
	// Κίνηση κομματιού
	$sql = 'call move_piece(?,?,?)';
	$st = $mysqli->prepare($sql);
	$st->bind_param("sii", $token, $from, $to);
	$st->execute();

	//Ενημέρωση top_color
	$st = $mysqli->prepare("SELECT w_count, b_count FROM board WHERE position = ?");
    $st->bind_param('i', $to);
    $st->execute();
    $dst = $st->get_result()->fetch_assoc();

	$st = $mysqli->prepare("SELECT w_count, b_count FROM board WHERE position = ?");
	$st->bind_param('i', $from);
	$st->execute();
	$src = $st->get_result()->fetch_assoc();
    
	$w_from = $src['w_count'];
	$b_from = $src['b_count'];
	$w_to = $dst['w_count'];
	$b_to = $dst['b_count'];

	if ($color == 'W'){
		if($b_to==1){
			$mysqli->query("UPDATE board SET top_color='W' WHERE position=$to"); //Πλάκωμα
		}elseif ($w_to>=2){
			$mysqli->query("UPDATE board SET top_color='W' WHERE position=$to");
		}
		
		if ($b_from<=1 && $w_from<=1){
			$mysqli->query("UPDATE board SET top_color=NULL WHERE position=$from");
		}
	} else {
		if($w_to==1){
			$mysqli->query("UPDATE board SET top_color='B' WHERE position=$to"); //Πλάκωμα
		}elseif ($b_to>=2){
			$mysqli->query("UPDATE board SET top_color='B' WHERE position=$to");
		}
		
		if ($w_from<=1 && $b_from<=1){
			$mysqli->query("UPDATE board SET top_color=NULL WHERE position=$from");
		}
	}
	//Έλεγχος για τον νικητή
	$st = $mysqli->prepare("SELECT w_count FROM board WHERE position = 25");
	$st->execute();
	$w_out = (int)$st->get_result()->fetch_row()[0];
	$st = $mysqli->prepare("SELECT b_count FROM board WHERE position = 0");
	$st->execute();
	$b_out = (int)$st->get_result()->fetch_row()[0];

	$st = $mysqli->prepare("SELECT w_count, b_count FROM board WHERE position = 1");
	$st->execute();
	$m1 = $st->get_result()->fetch_assoc();	
	$st = $mysqli->prepare("SELECT w_count, b_count FROM board WHERE position = 24");
	$st->execute();
	$m24 = $st->get_result()->fetch_assoc();

	$white_double = ($color === 'W' && $m24['b_count'] == 1 && $m24['w_count'] == 1) || 
	($w_out == 15 && $b_out == 0);
	$black_double = ($color === 'B' && $m1['w_count'] == 1 && $m1['b_count'] == 1) || 
	($b_out == 15 && $w_out == 0);

	if ($white_double) {
		$mysqli->query("UPDATE game_status SET status='ended', result='W', score='2'");
		print json_encode("White wins with a double score!");
		exit;
	}
	if ($black_double) {
		$mysqli->query("UPDATE game_status SET status='ended', result='B', score='2'");
		print json_encode("Black wins with a double score!");
		exit;
	} 
	if ($w_out == 15) {
		$mysqli->query("UPDATE game_status SET status='ended', result='W', score='1'");
		print json_encode("White wins!");
		exit;
	}
	if ($b_out == 15) {
		$mysqli->query("UPDATE game_status SET status='ended', result='B', score='1'");
		print json_encode("Black wins!");
		exit;
	}

	header('Content-type: application/json');
	print json_encode(read_board(), JSON_PRETTY_PRINT);

	// Αφαίρεση χρησιμοποιημένου ζαριού
	if ($status['dice3'] != null || $status['dice4'] != null) {
		$double_count = $status['double_count'];
		if($double_count < 4){
			$double_count ++;
			$mysqli->query("UPDATE game_status SET dice{$double_count}=NULL");
			$mysqli->query("UPDATE game_status SET double_count={$double_count}");
		} 
	}else {
		$mysqli->query("UPDATE game_status SET double_count=0");
		if ($distance == $status['dice1']) {
			$mysqli->query("UPDATE game_status SET dice1=NULL");
		} elseif ($distance == $status['dice2']) {
			$mysqli->query("UPDATE game_status SET dice2=NULL");
		} elseif ($distance == ($status['dice1'] + $status['dice2'])) {
			$mysqli->query("UPDATE game_status SET dice1=NULL, dice2=NULL");
		}
	}

	$status = read_status();

	//Αλλαγή σειράς
	if ($status['dice1'] == null && $status['dice2'] == null && $status['dice3'] == null && $status['dice4'] == null) {
        $next = ($color === 'W') ? 'B' : 'W';
        $mysqli->query("UPDATE game_status SET p_turn='$next'");
    }

}

function player_has_move($color, $status){
	global $mysqli;

	$dir = ($color === 'W') ? 1 : -1; //Κατεύθυνση κίνησης +1 για λευκό, -1 για μαύρο
	$dice = [];
	foreach (['dice1', 'dice2', 'dice3', 'dice4'] as $d) {
		if ($status[$d] != null) {
			$dice[] = $status[$d];
		}
	}

	// Έλεγχος για κάθε κομμάτι του παίκτη αν μπορεί να κινηθεί με κάποιο από τα ζάρια
	$field = ($color === 'W') ? 'w_count' : 'b_count';
	$res = $mysqli->query("SELECT position FROM board WHERE $field > 0 AND (top_color IS NULL OR top_color='$color')");

	while ($row = $res->fetch_assoc()) {
		$from = (int)$row['position']; 
		
		foreach ($dice as $d) {
			$to = $from + $dir*$d;
			if ($to < 0 || $to > 25) continue; //Έξοδος από το ταμπλό
			$st = $mysqli->prepare("SELECT w_count, b_count FROM board WHERE position = ?");
			$st->bind_param("i", $to);
			$st->execute();
			$dst = $st->get_result()->fetch_assoc();

			if ($color === 'W' && $dst['b_count'] >=2) continue; 
			if ($color === 'B' && $dst['w_count'] >=2) continue;

			return true; //Υπάρχει διαθέσιμη κίνηση
		}
	}

	return false;
}

?>