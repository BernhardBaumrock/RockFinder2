
		$database = $this->wire('database'); 
		$query = $database->prepare('DELETE FROM modules WHERE class=:class LIMIT 1'); // QA
		$query->bindValue(":class", $class, \PDO::PARAM_STR); 
		$query->execute();
