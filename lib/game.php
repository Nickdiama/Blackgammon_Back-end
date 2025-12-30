<?php

//Εμφανίζει την κατάσταση του παιχνιδιού
function show_status() {
  global $mysqli;
	
	$sql = 'select * from game_status';
	$st = $mysqli->prepare($sql);
	$st->execute();
	$res = $st->get_result();
	header('Content-type: application/json');
	print json_encode($res->fetch_all(MYSQLI_ASSOC), JSON_PRETTY_PRINT);
}

//Ενημερώνει την κατάσταση του παιχνιδιού
function update_game_status() {
	global $mysqli;

	// Διάβασε την τρέχουσα κατάσταση
	$sql = 'select * from game_status';
	$st = $mysqli->prepare($sql);
	$st->execute();
	$res = $st->get_result();
	$status = $res->fetch_assoc();
	
	// Δήλωση νέας κατάστασης
	$new_status=null;
	
	// Έλεγχος για ανενεργούς παίκτες
	$st3=$mysqli->prepare('select count(*) as aborted from players WHERE last_action< (NOW() - INTERVAL 20 MINUTE)');
	$st3->execute();
	$res3 = $st3->get_result();
	$aborted = $res3->fetch_assoc()['aborted']; 
	// Αν υπάρχουν ανενεργοί παίκτες, αφαίρεσέ τους
	if($aborted>0) {
		$sql = "UPDATE players SET username=NULL, token=NULL WHERE last_action< (NOW() - INTERVAL 20 MINUTE)";
		$st2 = $mysqli->prepare($sql);
		$st2->execute();
		// Αν το παιχνίδι ήταν σε εξέλιξη, άλλαξε την κατάσταση σε aborted
		if($status['status']=='started') {
			$new_status='aborted';
		}
	}

	// Μέτρησε τους ενεργούς παίκτες
	$sql = 'select count(*) as c from players where username is not null';
	$st = $mysqli->prepare($sql);
	$st->execute();
	$res = $st->get_result();
	$active_players = $res->fetch_assoc()['c'];
	
	// Καθορισμός νέας κατάστασης
	switch($active_players) {
		case 0: $new_status='not active'; break;
		case 1: $new_status='initialized'; break;
		// Αν μπήκαν δύο παίκτες, μηδένησε τα ζάρια εκκίνησης
		case 2: $new_status='started';
			$mysqli->query("UPDATE game_status SET start_dice_w = NULL, start_dice_b = NULL");
			break;
		}

	// Ενημέρωση κατάστασης παιχνιδιού
	$sql = 'update game_status set status=?';
	$st = $mysqli->prepare($sql);
	$st->bind_param('s',$new_status);
	$st->execute();
}

//Επιστρέφει την κατάσταση του παιχνιδιού
function read_status() {
	global $mysqli;
	
	$sql = 'select * from game_status';
	$st = $mysqli->prepare($sql);
	$st->execute();
	$res = $st->get_result();
	$status = $res->fetch_assoc();
	return($status);
}

?>