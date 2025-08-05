<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

class LovePhotoAPI {
    private $uploadDir = 'uploads/';
    private $dataFile = 'love_data.json';
    
    public function __construct() {
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        switch($method) {
            case 'GET':
                $this->getPhotos();
                break;
            case 'POST':
                $this->uploadPhoto();
                break;
            case 'DELETE':
                $this->deletePhoto();
                break;
            default:
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
        }
    }
    
    private function getPhotos() {
        if (file_exists($this->dataFile)) {
            $data = json_decode(file_get_contents($this->dataFile), true);
            echo json_encode($data ?: []);
        } else {
            echo json_encode([]);
        }
    }
    
    private function uploadPhoto() {
        if (!isset($_FILES['photo'])) {
            http_response_code(400);
            echo json_encode(['error' => 'No photo uploaded']);
            return;
        }
        
        $file = $_FILES['photo'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if (!in_array($file['type'], $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid file type']);
            return;
        }
        
        $filename = uniqid('love_') . '_' . basename($file['name']);
        $filepath = $this->uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $photoData = [
                'id' => uniqid(),
                'filename' => $filename,
                'path' => $filepath,
                'upload_time' => date('Y-m-d H:i:s'),
                'caption' => $_POST['caption'] ?? 'Kenangan Indah Kita'
            ];
            
            $this->savePhotoData($photoData);
            echo json_encode(['success' => true, 'data' => $photoData]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Upload failed']);
        }
    }
    
    private function deletePhoto() {
        $input = json_decode(file_get_contents('php://input'), true);
        $photoId = $input['id'] ?? null;
        
        if (!$photoId) {
            http_response_code(400);
            echo json_encode(['error' => 'Photo ID required']);
            return;
        }
        
        $data = $this->getPhotoData();
        $photoIndex = array_search($photoId, array_column($data, 'id'));
        
        if ($photoIndex !== false) {
            $photo = $data[$photoIndex];
            if (file_exists($photo['path'])) {
                unlink($photo['path']);
            }
            array_splice($data, $photoIndex, 1);
            file_put_contents($this->dataFile, json_encode($data));
            echo json_encode(['success' => true]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Photo not found']);
        }
    }
    
    private function savePhotoData($photoData) {
        $data = $this->getPhotoData();
        $data[] = $photoData;
        file_put_contents($this->dataFile, json_encode($data));
    }
    
    private function getPhotoData() {
        if (file_exists($this->dataFile)) {
            return json_decode(file_get_contents($this->dataFile), true) ?: [];
        }
        return [];
    }
}

$api = new LovePhotoAPI();
$api->handleRequest();
?>
class LoveAPI {
    private $dataFile = 'love_data.json';
    
    public function __construct() {
        if (!file_exists($this->dataFile)) {
            file_put_contents($this->dataFile, json_encode([
                'memories' => [],
                'photos' => [],
                'messages' => [],
                'love_scores' => []
            ]));
        }
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $_GET['action'] ?? '';
        
        switch ($method) {
            case 'GET':
                $this->handleGet($action);
                break;
            case 'POST':
                $this->handlePost($action);
                break;
            case 'DELETE':
                $this->handleDelete($action);
                break;
            default:
                $this->sendResponse(['error' => 'Method not allowed'], 405);
        }
    }
    
    private function handleGet($action) {
        $data = $this->loadData();
        
        switch ($action) {
            case 'memories':
                $this->sendResponse($data['memories']);
                break;
            case 'photos':
                $this->sendResponse($data['photos']);
                break;
            case 'messages':
                $this->sendResponse($data['messages']);
                break;
            case 'love_quote':
                $this->sendResponse($this->generateLoveQuote());
                break;
            case 'weather':
                $this->sendResponse($this->getLoveWeather());
                break;
            default:
                $this->sendResponse($data);
        }
    }
    
    private function handlePost($action) {
        $input = json_decode(file_get_contents('php://input'), true);
        $data = $this->loadData();
        
        switch ($action) {
            case 'memory':
                $memory = [
                    'id' => uniqid(),
                    'title' => $input['title'],
                    'date' => $input['date'],
                    'place' => $input['place'],
                    'story' => $input['story'],
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $data['memories'][] = $memory;
                $this->saveData($data);
                $this->sendResponse($memory);
                break;
                
            case 'photo':
                $photo = [
                    'id' => uniqid(),
                    'url' => $input['url'],
                    'title' => $input['title'],
                    'description' => $input['description'],
                    'uploaded_at' => date('Y-m-d H:i:s')
                ];
                $data['photos'][] = $photo;
                $this->saveData($data);
                $this->sendResponse($photo);
                break;
                
            case 'message':
                $message = [
                    'id' => uniqid(),
                    'text' => $input['text'],
                    'sender' => $input['sender'] ?? 'User',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                $data['messages'][] = $message;
                
                // Generate AI response
                $aiResponse = $this->generateAIResponse($input['text']);
                $data['messages'][] = [
                    'id' => uniqid(),
                    'text' => $aiResponse,
                    'sender' => 'AI',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                
                $this->saveData($data);
                $this->sendResponse(['user_message' => $message, 'ai_response' => $aiResponse]);
                break;
                
            case 'love_score':
                $score = $this->calculateAdvancedLoveScore($input);
                $data['love_scores'][] = [
                    'id' => uniqid(),
                    'name1' => $input['name1'],
                    'name2' => $input['name2'],
                    'score' => $score,
                    'calculated_at' => date('Y-m-d H:i:s')
                ];
                $this->saveData($data);
                $this->sendResponse(['score' => $score]);
                break;
                
            default:
                $this->sendResponse(['error' => 'Invalid action'], 400);
        }
    }
    
    private function handleDelete($action) {
        $id = $_GET['id'] ?? '';
        $data = $this->loadData();
        
        switch ($action) {
            case 'memory':
                $data['memories'] = array_filter($data['memories'], function($memory) use ($id) {
                    return $memory['id'] !== $id;
                });
                $this->saveData($data);
                $this->sendResponse(['success' => true]);
                break;
                
            case 'photo':
                $data['photos'] = array_filter($data['photos'], function($photo) use ($id) {
                    return $photo['id'] !== $id;
                });
                $this->saveData($data);
                $this->sendResponse(['success' => true]);
                break;
                
            default:
                $this->sendResponse(['error' => 'Invalid action'], 400);
        }
    }
    
    private function loadData() {
        return json_decode(file_get_contents($this->dataFile), true);
    }
    
    private function saveData($data) {
        file_put_contents($this->dataFile, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    private function calculateAdvancedLoveScore($input) {
        $name1 = strtolower($input['name1']);
        $name2 = strtolower($input['name2']);
        
        // Advanced algorithm considering multiple factors
        $score = 0;
        
        // Name compatibility
        $combined = $name1 . $name2;
        for ($i = 0; $i < strlen($combined); $i++) {
            $score += ord($combined[$i]);
        }
        
        // Length compatibility
        $lengthDiff = abs(strlen($name1) - strlen($name2));
        $score += (10 - $lengthDiff) * 5;
        
        // Common letters
        $common = array_intersect(str_split($name1), str_split($name2));
        $score += count($common) * 10;
        
        // Vowel compatibility
        $vowels1 = preg_match_all('/[aeiou]/', $name1);
        $vowels2 = preg_match_all('/[aeiou]/', $name2);
        $score += abs($vowels1 - $vowels2) * 3;
        
        // Date compatibility if provided
        if (isset($input['date1']) && isset($input['date2'])) {
            $date1 = new DateTime($input['date1']);
            $date2 = new DateTime($input['date2']);
            $dayDiff = abs($date1->format('j') - $date2->format('j'));
            $monthDiff = abs($date1->format('n') - $date2->format('n'));
            $score += (31 - $dayDiff) + (13 - $monthDiff) * 2;
        }
        
        // Normalize to percentage
        $percentage = ($score % 100) + 1;
        
        // Ensure minimum romantic score
        if ($percentage < 60) {
            $percentage += 20;
        }
        
        return min($percentage, 100);
    }
    
    private function generateAIResponse($message) {
        $responses = [
            'cinta' => [
                '💕 Cinta adalah perasaan terindah di dunia!',
                '💖 Ceritakan lebih banyak tentang cinta kalian!',
                '🌹 Cinta sejati selalu menemukan jalannya.'
            ],
            'rindu' => [
                '💔 Rindu adalah bukti cinta yang mendalam.',
                '🌙 Rindu membuat jarak terasa dekat.',
                '💕 Rindu adalah bahasa hati yang paling jujur.'
            ],
            'bahagia' => [
                '😊 Kebahagiaan kalian sangat menular!',
                '✨ Bahagia bersama adalah anugerah terindah.',
                '🎉 Semoga kebahagiaan kalian selalu berlimpah!'
            ],
            'sedih' => [
                '🤗 Semua akan baik-baik saja, cinta akan menyembuhkan.',
                '💪 Kalian kuat bersama-sama.',
                '🌈 Setelah hujan pasti ada pelangi.'
            ]
        ];
        
        $message = strtolower($message);
        
        foreach ($responses as $keyword => $responseList) {
            if (strpos($message, $keyword) !== false) {
                return $responseList[array_rand($responseList)];
            }
        }
        
        // Default responses
        $defaultResponses = [
            '💕 Itu sangat menarik! Ceritakan lebih