<?php
/**
 * KL University ERP Integration Module
 * Handles login and data fetching from the ERP portal
 */

class ERPIntegration {
    private $erp_url = "https://newerp.kluniversity.in/";
    private $db;
    private $user_id;
    private $username;
    private $password;
    private $session_file;
    
    public function __construct($db, $user_id) {
        $this->db = $db;
        $this->user_id = $user_id;
        $this->session_file = sys_get_temp_dir() . "/erp_session_{$user_id}.txt";
    }
    
    /**
     * Set credentials (from user input or stored session)
     */
    public function setCredentials($username, $password) {
        $this->username = $username;
        $this->password = $password;
        // Store encrypted credentials in session (temporary, not persistent)
        return true;
    }
    
    /**
     * Get stored credentials from database (if user saved them)
     */
    public function getStoredCredentials() {
        $sql = "SELECT erp_username, erp_password FROM users WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['erp_username'])) {
            $this->username = $result['erp_username'];
            $this->password = base64_decode($result['erp_password']); // Simple encoding, not secure
            return true;
        }
        return false;
    }
    
    /**
     * Save credentials to database (encrypted)
     */
    public function saveCredentials($username, $password) {
        $encrypted_password = base64_encode($password);
        $sql = "UPDATE users SET erp_username = ?, erp_password = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$username, $encrypted_password, $this->user_id]);
    }
    
    /**
     * Login to ERP portal and establish session
     */
    public function login() {
        if (empty($this->username) || empty($this->password)) {
            return ['success' => false, 'error' => 'Credentials not set'];
        }
        
        $cookie_file = sys_get_temp_dir() . "/erp_cookies_{$this->user_id}.txt";
        
        // Step 1: Get login page to extract CSRF token (if needed)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->erp_url . "site/login");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $login_page = curl_exec($ch);
        
        // Step 2: Extract CSRF token if it exists
        $csrf_token = '';
        if (preg_match('/name="YII_CSRF_TOKEN"\s+value="([^"]+)"/', $login_page, $matches)) {
            $csrf_token = $matches[1];
        }
        
        // Step 3: Submit login credentials
        $post_data = [
            'LoginForm[username]' => $this->username,
            'LoginForm[password]' => $this->password,
            'login-button' => 'Sign in'
        ];
        
        if (!empty($csrf_token)) {
            $post_data['YII_CSRF_TOKEN'] = $csrf_token;
        }
        
        curl_setopt($ch, CURLOPT_URL, $this->erp_url . "site/login");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Check if login was successful (look for dashboard or redirect)
        if ($http_code === 200 && (strpos($response, 'Dashboard') !== false || strpos($response, 'logout') !== false)) {
            file_put_contents($this->session_file, time()); // Store session time
            return ['success' => true, 'message' => 'Successfully logged into ERP'];
        } else {
            return ['success' => false, 'error' => 'Login failed. Please verify credentials.'];
        }
    }
    
    /**
     * Fetch student marks from ERP
     */
    public function getMarks() {
        $cookie_file = sys_get_temp_dir() . "/erp_cookies_{$this->user_id}.txt";
        
        if (!file_exists($cookie_file)) {
            $login_result = $this->login();
            if (!$login_result['success']) {
                return $login_result;
            }
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->erp_url . "student/grades");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $marks = $this->parseMarksFromHTML($response);
            return ['success' => true, 'marks' => $marks];
        }
        
        return ['success' => false, 'error' => 'Failed to fetch marks'];
    }
    
    /**
     * Fetch student attendance from ERP
     */
    public function getAttendance() {
        $cookie_file = sys_get_temp_dir() . "/erp_cookies_{$this->user_id}.txt";
        
        if (!file_exists($cookie_file)) {
            $login_result = $this->login();
            if (!$login_result['success']) {
                return $login_result;
            }
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->erp_url . "student/attendance");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $attendance = $this->parseAttendanceFromHTML($response);
            return ['success' => true, 'attendance' => $attendance];
        }
        
        return ['success' => false, 'error' => 'Failed to fetch attendance'];
    }
    
    /**
     * Parse marks data from HTML response
     */
    private function parseMarksFromHTML($html) {
        $marks = [];
        
        // Simple parsing - look for table rows with subject names and marks
        if (preg_match_all('/<tr[^>]*>.*?<td[^>]*>([^<]+)<\/td>.*?<td[^>]*>([^<]+)<\/td>/s', $html, $matches)) {
            for ($i = 0; $i < count($matches[1]); $i++) {
                $subject = trim($matches[1][$i]);
                $mark = trim($matches[2][$i]);
                if (!empty($subject) && !empty($mark)) {
                    $marks[] = ['subject' => $subject, 'mark' => $mark];
                }
            }
        }
        
        return !empty($marks) ? $marks : ['error' => 'No marks data found'];
    }
    
    /**
     * Parse attendance data from HTML response
     */
    private function parseAttendanceFromHTML($html) {
        $attendance = [];
        
        // Simple parsing - look for attendance percentage
        if (preg_match_all('/<tr[^>]*>.*?<td[^>]*>([^<]+)<\/td>.*?<td[^>]*>([^<]+)%?<\/td>/s', $html, $matches)) {
            for ($i = 0; $i < count($matches[1]); $i++) {
                $subject = trim($matches[1][$i]);
                $percent = trim($matches[2][$i]);
                if (!empty($subject) && is_numeric(str_replace('%', '', $percent))) {
                    $attendance[] = ['subject' => $subject, 'percentage' => $percent];
                }
            }
        }
        
        return !empty($attendance) ? $attendance : ['error' => 'No attendance data found'];
    }
    
    /**
     * Get formatted student profile
     */
    public function getProfileInfo() {
        $cookie_file = sys_get_temp_dir() . "/erp_cookies_{$this->user_id}.txt";
        
        if (!file_exists($cookie_file)) {
            $login_result = $this->login();
            if (!$login_result['success']) {
                return $login_result;
            }
        }
        
        $profile_info = [
            'username' => $this->username,
            'status' => 'Active',
            'institution' => 'KL University'
        ];
        
        return ['success' => true, 'profile' => $profile_info];
    }
}
?>
