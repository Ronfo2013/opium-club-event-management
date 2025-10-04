<?php
/**
 * Birthday Template Manager - Gestione template tramite file JSON
 * Sostituisce il database per la gestione dei template di compleanno
 */

class BirthdayTemplateManager
{
    private $templatesListFile;
    private $templatesDir;
    
    public function __construct($templatesDir = __DIR__)
    {
        $this->templatesDir = $templatesDir;
        $this->templatesListFile = $templatesDir . '/birthday_templates.json';
        
        // Crea il file dei template se non esiste
        $this->initializeTemplates();
    }
    
    /**
     * Inizializza i template se il file non esiste
     */
    private function initializeTemplates()
    {
        if (!file_exists($this->templatesListFile)) {
            $defaultTemplates = [
                [
                    'id' => 1,
                    'name' => 'Template Default MrCharlie',
                    'subject' => '🎉 Buon Compleanno da MrCharlie! 🎂',
                    'filename' => 'template_default.json',
                    'is_active' => true,
                    'times_used' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            ];
            
            file_put_contents($this->templatesListFile, json_encode($defaultTemplates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
    
    /**
     * Ottiene tutti i template
     */
    public function getAllTemplates()
    {
        if (!file_exists($this->templatesListFile)) {
            return [];
        }
        
        $templates = json_decode(file_get_contents($this->templatesListFile), true);
        
        // Aggiungi il contenuto HTML di ogni template
        foreach ($templates as &$template) {
            $templateContent = $this->getTemplateContent($template['filename']);
            if ($templateContent) {
                $template['html_content'] = $templateContent['html_content'];
                $template['background_image'] = $templateContent['background_image'] ?? null;
            }
        }
        
        return $templates ?: [];
    }
    
    /**
     * Ottiene un template specifico per ID
     */
    public function getTemplate($id)
    {
        $templates = $this->getAllTemplates();
        
        foreach ($templates as $template) {
            if ($template['id'] == $id) {
                return $template;
            }
        }
        
        return null;
    }
    
    /**
     * Ottiene il contenuto di un template dal file JSON
     */
    private function getTemplateContent($filename)
    {
        $filePath = $this->templatesDir . '/' . $filename;
        
        if (!file_exists($filePath)) {
            return null;
        }
        
        return json_decode(file_get_contents($filePath), true);
    }
    
    /**
     * Salva un template (nuovo o aggiornamento)
     */
    public function saveTemplate($data)
    {
        try {
            $templates = json_decode(file_get_contents($this->templatesListFile), true) ?: [];
            $isUpdate = false;
            
            // Se è un aggiornamento
            if (isset($data['id']) && $data['id'] > 0) {
                foreach ($templates as &$template) {
                    if ($template['id'] == $data['id']) {
                        $template['name'] = $data['name'];
                        $template['subject'] = $data['subject'];
                        $template['updated_at'] = date('Y-m-d H:i:s');
                        $isUpdate = true;
                        break;
                    }
                }
            }
            
            // Se è un nuovo template
            if (!$isUpdate) {
                $newId = $this->getNextId($templates);
                $filename = 'template_' . strtolower(str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9\s]/', '', $data['name']))) . '.json';
                
                $newTemplate = [
                    'id' => $newId,
                    'name' => $data['name'],
                    'subject' => $data['subject'],
                    'filename' => $filename,
                    'is_active' => false,
                    'times_used' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $templates[] = $newTemplate;
                $data['id'] = $newId;
            }
            
            // Salva la lista aggiornata
            file_put_contents($this->templatesListFile, json_encode($templates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            // Salva il contenuto del template
            $this->saveTemplateContent($data);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Errore salvataggio template: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Salva il contenuto HTML del template
     */
    private function saveTemplateContent($data)
    {
        $templates = json_decode(file_get_contents($this->templatesListFile), true);
        $filename = null;
        
        // Trova il filename del template
        foreach ($templates as $template) {
            if ($template['id'] == $data['id']) {
                $filename = $template['filename'];
                break;
            }
        }
        
        if (!$filename) {
            return false;
        }
        
        $templateContent = [
            'id' => $data['id'],
            'name' => $data['name'],
            'subject' => $data['subject'],
            'html_content' => $data['html_content'],
            'background_image' => $data['background_image'] ?? null,
            'variables_used' => $this->extractVariables($data['html_content']),
            'description' => $data['description'] ?? 'Template personalizzato',
            'category' => $data['category'] ?? 'custom'
        ];
        
        $filePath = $this->templatesDir . '/' . $filename;
        return file_put_contents($filePath, json_encode($templateContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }
    
    /**
     * Estrae le variabili dal contenuto HTML
     */
    private function extractVariables($htmlContent)
    {
        preg_match_all('/\{\{([A-Z_]+)\}\}/', $htmlContent, $matches);
        return array_unique($matches[0]);
    }
    
    /**
     * Ottiene il prossimo ID disponibile
     */
    private function getNextId($templates)
    {
        $maxId = 0;
        foreach ($templates as $template) {
            if ($template['id'] > $maxId) {
                $maxId = $template['id'];
            }
        }
        return $maxId + 1;
    }
    
    /**
     * Attiva un template (disattiva tutti gli altri)
     */
    public function activateTemplate($id)
    {
        try {
            $templates = json_decode(file_get_contents($this->templatesListFile), true) ?: [];
            
            // Disattiva tutti i template
            foreach ($templates as &$template) {
                $template['is_active'] = false;
            }
            
            // Attiva il template richiesto
            foreach ($templates as &$template) {
                if ($template['id'] == $id) {
                    $template['is_active'] = true;
                    $template['updated_at'] = date('Y-m-d H:i:s');
                    break;
                }
            }
            
            file_put_contents($this->templatesListFile, json_encode($templates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return true;
            
        } catch (Exception $e) {
            error_log("Errore attivazione template: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Elimina un template
     */
    public function deleteTemplate($id)
    {
        try {
            $templates = json_decode(file_get_contents($this->templatesListFile), true) ?: [];
            
            // Controlla se è l'ultimo template
            if (count($templates) <= 1) {
                throw new Exception("Non puoi eliminare l'ultimo template");
            }
            
            $templateToDelete = null;
            foreach ($templates as $template) {
                if ($template['id'] == $id) {
                    if ($template['is_active']) {
                        throw new Exception("Non puoi eliminare il template attivo");
                    }
                    $templateToDelete = $template;
                    break;
                }
            }
            
            if (!$templateToDelete) {
                throw new Exception("Template non trovato");
            }
            
            // Rimuovi dalla lista
            $templates = array_filter($templates, function($template) use ($id) {
                return $template['id'] != $id;
            });
            
            // Elimina il file del template
            $filePath = $this->templatesDir . '/' . $templateToDelete['filename'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // Salva la lista aggiornata
            file_put_contents($this->templatesListFile, json_encode(array_values($templates), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            return true;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Ottiene il template attivo
     */
    public function getActiveTemplate()
    {
        $templates = $this->getAllTemplates();
        
        foreach ($templates as $template) {
            if ($template['is_active']) {
                return $template;
            }
        }
        
        // Se nessun template è attivo, attiva il primo
        if (!empty($templates)) {
            $this->activateTemplate($templates[0]['id']);
            return $templates[0];
        }
        
        return null;
    }
    
    /**
     * Ottiene statistiche sui template
     */
    public function getBirthdayStats()
    {
        $templates = $this->getAllTemplates();
        
        return [
            'today_birthdays' => 2, // Mock data
            'sent_this_year' => 47,
            'upcoming_birthdays' => 8,
            'total_templates' => count($templates)
        ];
    }
    
    /**
     * Ottiene i prossimi compleanni (mock data)
     */
    public function getUpcomingBirthdays($days = 30)
    {
        return [
            [
                'nome' => 'Mario',
                'cognome' => 'Rossi',
                'email' => 'mario.rossi@email.com',
                'birthday_display' => '25/06',
                'days_until' => 0
            ],
            [
                'nome' => 'Anna',
                'cognome' => 'Verdi',
                'email' => 'anna.verdi@email.com',
                'birthday_display' => '28/06',
                'days_until' => 3
            ],
            [
                'nome' => 'Luca',
                'cognome' => 'Bianchi',
                'email' => 'luca.bianchi@email.com',
                'birthday_display' => '02/07',
                'days_until' => 7
            ]
        ];
    }
    
    /**
     * Test di un template (simulato)
     */
    public function testTemplate($templateId, $testEmail)
    {
        $template = $this->getTemplate($templateId);
        if (!$template) {
            return false;
        }
        
        // Simula l'invio dell'email di test
        error_log("Test email inviata a: $testEmail con template: " . $template['name']);
        return true;
    }
    
    /**
     * Incrementa il contatore di utilizzo di un template
     */
    public function incrementUsage($id)
    {
        try {
            $templates = json_decode(file_get_contents($this->templatesListFile), true) ?: [];
            
            foreach ($templates as &$template) {
                if ($template['id'] == $id) {
                    $template['times_used']++;
                    $template['updated_at'] = date('Y-m-d H:i:s');
                    break;
                }
            }
            
            file_put_contents($this->templatesListFile, json_encode($templates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return true;
            
        } catch (Exception $e) {
            error_log("Errore incremento utilizzo: " . $e->getMessage());
            return false;
        }
    }
}
?> 