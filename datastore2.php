<?php

$servername = "localhost";
$username = "username";
$password = "password";
$dbname = "datastore";

try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // sql to create table
                        $sql = "CREATE TABLE Invoice (
                            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, 
                                 VARCHAR(30) NOT NULL,
                                    ContactId VARCHAR(30) NOT NULL,
                                        Paystatus VARCHAR(50),
                                            reg_date TIMESTAMP
                                                )";

                                                    // use exec() because no results are returned
                                                        $conn->exec($sql);
                                                            echo "Table MyGuests created successfully";
                                                                }
                                                                catch(PDOException $e)
                                                                    {
                                                                            echo $sql . "<br>" . $e->getMessage();
                                                                                }
                                                                                $conn = null;
        public class Mysql {
   
        public function mysql_insert($table, $inserts) {
            $values = array_map('mysql_real_escape_string', array_values($inserts));
            $keys = array_keys($inserts);
                    
                return mysql_query('INSERT INTO `'.$table.'` (`'.implode('`,`', $keys).'`) VALUES (\''.implode('\',\'', $values).'\')');
}

                 mysql_insert('Invoice', array(
                              'ContactId' =>$contact_id,
                              'PayStatus' => 1,
                              'RefundStatus' => 0,
                             ));
 }

 ?>
