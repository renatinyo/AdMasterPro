<?php
/**
 * AdMaster Pro - Project Manager
 * 
 * Projektek mentése, betöltése, törlése
 * Biztonságos fájlkezelés
 */

class ProjectManager {
    
    private string $projectsDir;
    private string $userId;

    public function __construct() {
        $this->projectsDir = PROJECTS_DIR;
        $this->userId = $this->getUserId();
        $this->ensureUserDir();
    }

    /**
     * User azonosító generálás (session alapú)
     */
    private function getUserId(): string {
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['user_id'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['user_id'];
    }

    /**
     * User könyvtár biztosítása
     */
    private function ensureUserDir(): void {
        $userDir = $this->getUserDir();
        if (!is_dir($userDir)) {
            mkdir($userDir, 0750, true);
        }
    }

    /**
     * User könyvtár elérési út
     */
    private function getUserDir(): string {
        // Biztonságos: user_id csak hex karaktereket tartalmaz
        return $this->projectsDir . $this->userId . '/';
    }

    /**
     * Projekt mentés
     */
    public function save(string $name, array $data): array {
        // Validálás
        $name = Security::sanitizeInput($name, 'filename');
        if (empty($name)) {
            return ['success' => false, 'error' => 'A projekt név kötelező.'];
        }

        // Limit ellenőrzés
        $projects = $this->list();
        if (count($projects) >= MAX_PROJECTS_PER_USER) {
            return ['success' => false, 'error' => 'Elérted a maximum projekt számot (' . MAX_PROJECTS_PER_USER . ').'];
        }

        // Projekt ID generálás
        $projectId = $this->generateProjectId();
        
        // Projekt adatok
        $project = [
            'id' => $projectId,
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'data' => $data
        ];

        // Mentés
        $filename = $this->getUserDir() . $projectId . '.json';
        $json = json_encode($project, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        if (file_put_contents($filename, $json, LOCK_EX) === false) {
            return ['success' => false, 'error' => 'Nem sikerült menteni a projektet.'];
        }

        Security::log('info', 'Project saved', ['project_id' => $projectId, 'name' => $name]);

        return ['success' => true, 'project_id' => $projectId, 'message' => 'Projekt sikeresen mentve!'];
    }

    /**
     * Projekt frissítés
     */
    public function update(string $projectId, array $data, ?string $newName = null): array {
        $projectId = Security::sanitizeInput($projectId, 'alphanumeric');
        
        $existing = $this->load($projectId);
        if (!$existing['success']) {
            return $existing;
        }

        $project = $existing['project'];
        $project['data'] = $data;
        $project['updated_at'] = date('Y-m-d H:i:s');
        
        if ($newName !== null) {
            $project['name'] = Security::sanitizeInput($newName, 'filename');
        }

        $filename = $this->getUserDir() . $projectId . '.json';
        $json = json_encode($project, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        if (file_put_contents($filename, $json, LOCK_EX) === false) {
            return ['success' => false, 'error' => 'Nem sikerült frissíteni a projektet.'];
        }

        return ['success' => true, 'message' => 'Projekt frissítve!'];
    }

    /**
     * Projekt betöltés
     */
    public function load(string $projectId): array {
        $projectId = Security::sanitizeInput($projectId, 'alphanumeric');
        $filename = $this->getUserDir() . $projectId . '.json';

        // Path traversal védelem
        $realPath = realpath($filename);
        $userDir = realpath($this->getUserDir());
        
        if ($realPath === false || strpos($realPath, $userDir) !== 0) {
            return ['success' => false, 'error' => 'Projekt nem található.'];
        }

        if (!file_exists($filename)) {
            return ['success' => false, 'error' => 'Projekt nem található.'];
        }

        $content = file_get_contents($filename);
        $project = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => 'Hibás projekt fájl.'];
        }

        return ['success' => true, 'project' => $project];
    }

    /**
     * Projekt törlés
     */
    public function delete(string $projectId): array {
        $projectId = Security::sanitizeInput($projectId, 'alphanumeric');
        $filename = $this->getUserDir() . $projectId . '.json';

        // Path traversal védelem
        $realPath = realpath($filename);
        $userDir = realpath($this->getUserDir());
        
        if ($realPath === false || strpos($realPath, $userDir) !== 0) {
            return ['success' => false, 'error' => 'Projekt nem található.'];
        }

        if (!file_exists($filename)) {
            return ['success' => false, 'error' => 'Projekt nem található.'];
        }

        if (!unlink($filename)) {
            return ['success' => false, 'error' => 'Nem sikerült törölni a projektet.'];
        }

        Security::log('info', 'Project deleted', ['project_id' => $projectId]);

        return ['success' => true, 'message' => 'Projekt törölve!'];
    }

    /**
     * Projektek listázása
     */
    public function list(): array {
        $userDir = $this->getUserDir();
        $projects = [];

        if (!is_dir($userDir)) {
            return $projects;
        }

        $files = glob($userDir . '*.json');
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $project = json_decode($content, true);
            
            if ($project) {
                $projects[] = [
                    'id' => $project['id'],
                    'name' => $project['name'],
                    'created_at' => $project['created_at'],
                    'updated_at' => $project['updated_at'],
                    'industry' => $project['data']['industry'] ?? 'unknown'
                ];
            }
        }

        // Rendezés: legújabb elöl
        usort($projects, fn($a, $b) => strtotime($b['updated_at']) - strtotime($a['updated_at']));

        return $projects;
    }

    /**
     * Projekt ID generálás
     */
    private function generateProjectId(): string {
        return date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Összes projekt törlése
     */
    public function deleteAll(): array {
        $userDir = $this->getUserDir();
        $files = glob($userDir . '*.json');
        
        $deleted = 0;
        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted++;
            }
        }

        return ['success' => true, 'deleted' => $deleted, 'message' => "$deleted projekt törölve."];
    }

    /**
     * Projekt export (JSON)
     */
    public function export(string $projectId): ?string {
        $result = $this->load($projectId);
        if (!$result['success']) {
            return null;
        }

        return json_encode($result['project'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Projekt import (JSON)
     */
    public function import(string $jsonContent): array {
        $project = json_decode($jsonContent, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => 'Hibás JSON formátum.'];
        }

        if (!isset($project['name']) || !isset($project['data'])) {
            return ['success' => false, 'error' => 'Hiányzó projekt adatok.'];
        }

        return $this->save($project['name'] . ' (imported)', $project['data']);
    }
}
