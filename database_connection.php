<?php
class Database
{
    // Database credentials
    private $servername = "localhost";
    private $username = "id21953222_wpdetector";
    private $password = "W3B3%i2@";
    private $dbname = "id21953222_wpdetector";
    private $conn;

    public function connect()
    {
        $this->conn = new mysqli($this->servername, $this->username, $this->password, $this->dbname);

        // Check if the connection was successful
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    public function query($sql)
    {
        $result = $this->conn->query($sql);

        // Check if the query execution was successful
        if ($result === FALSE) {
            die("Error: " . $sql . "<br>" . $this->conn->error);
        }

        return $result;
    }

    public function close()
    {
        $this->conn->close();
    }
}
?>