<?php

/**
 * Validateur de formulaires côté serveur
 * Validation complète avec règles personnalisables et messages d'erreur
 */
class FormValidator {
    
    private array $data = [];
    private array $errors = [];
    private array $rules = [];
    private array $messages = [];
    
    // Messages d'erreur par défaut
    private static array $defaultMessages = [
        'required' => 'Le champ :field est obligatoire',
        'email' => 'Le champ :field doit être une adresse email valide',
        'min' => 'Le champ :field doit contenir au moins :min caractères',
        'max' => 'Le champ :field ne peut pas dépasser :max caractères',
        'numeric' => 'Le champ :field doit être numérique',
        'integer' => 'Le champ :field doit être un nombre entier',
        'alpha' => 'Le champ :field ne peut contenir que des lettres',
        'alphanumeric' => 'Le champ :field ne peut contenir que des lettres et des chiffres',
        'regex' => 'Le format du champ :field est invalide',
        'same' => 'Le champ :field doit être identique à :other',
        'different' => 'Le champ :field doit être différent de :other',
        'in' => 'Le champ :field doit être une des valeurs suivantes: :values',
        'not_in' => 'Le champ :field ne peut pas être une des valeurs suivantes: :values',
        'url' => 'Le champ :field doit être une URL valide',
        'date' => 'Le champ :field doit être une date valide',
        'boolean' => 'Le champ :field doit être vrai ou faux',
        'file' => 'Le champ :field doit être un fichier',
        'image' => 'Le champ :field doit être une image',
        'mimes' => 'Le champ :field doit être un fichier de type: :types',
        'max_size' => 'Le fichier :field ne peut pas dépasser :size Mo',
        'strong_password' => 'Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial'
    ];
    
    public function __construct(array $data = []) {
        $this->data = $this->sanitizeData($data);
    }
    
    /**
     * Sanitise les données d'entrée
     */
    private function sanitizeData(array $data): array {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = SecurityManager::sanitizeInput($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
    
    /**
     * Ajoute des règles de validation
     */
    public function rules(array $rules): self {
        $this->rules = $rules;
        return $this;
    }
    
    /**
     * Ajoute des messages personnalisés
     */
    public function messages(array $messages): self {
        $this->messages = array_merge($this->messages, $messages);
        return $this;
    }
    
    /**
     * Valide les données selon les règles
     */
    public function validate(): bool {
        $this->errors = [];
        
        foreach ($this->rules as $field => $rules) {
            $fieldRules = is_string($rules) ? explode('|', $rules) : $rules;
            
            foreach ($fieldRules as $rule) {
                $this->validateField($field, $rule);
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Valide un champ spécifique
     */
    private function validateField(string $field, string $rule): void {
        $ruleParts = explode(':', $rule);
        $ruleName = $ruleParts[0];
        $ruleParams = isset($ruleParts[1]) ? explode(',', $ruleParts[1]) : [];
        
        $value = $this->getValue($field);
        $isValid = true;
        
        switch ($ruleName) {
            case 'required':
                $isValid = $this->validateRequired($value);
                break;
                
            case 'email':
                $isValid = $this->validateEmail($value);
                break;
                
            case 'min':
                $isValid = $this->validateMin($value, (int)$ruleParams[0]);
                break;
                
            case 'max':
                $isValid = $this->validateMax($value, (int)$ruleParams[0]);
                break;
                
            case 'numeric':
                $isValid = $this->validateNumeric($value);
                break;
                
            case 'integer':
                $isValid = $this->validateInteger($value);
                break;
                
            case 'alpha':
                $isValid = $this->validateAlpha($value);
                break;
                
            case 'alphanumeric':
                $isValid = $this->validateAlphanumeric($value);
                break;
                
            case 'regex':
                $isValid = $this->validateRegex($value, $ruleParams[0]);
                break;
                
            case 'same':
                $isValid = $this->validateSame($value, $ruleParams[0]);
                break;
                
            case 'different':
                $isValid = $this->validateDifferent($value, $ruleParams[0]);
                break;
                
            case 'in':
                $isValid = $this->validateIn($value, $ruleParams);
                break;
                
            case 'not_in':
                $isValid = $this->validateNotIn($value, $ruleParams);
                break;
                
            case 'url':
                $isValid = $this->validateUrl($value);
                break;
                
            case 'date':
                $isValid = $this->validateDate($value);
                break;
                
            case 'boolean':
                $isValid = $this->validateBoolean($value);
                break;
                
            case 'strong_password':
                $isValid = $this->validateStrongPassword($value);
                break;
                
            case 'username':
                $isValid = $this->validateUsername($value);
                break;
                
            case 'safe_text':
                $isValid = $this->validateSafeText($value);
                break;
        }
        
        if (!$isValid) {
            $this->addError($field, $ruleName, $ruleParams);
        }
    }
    
    /**
     * Récupère la valeur d'un champ
     */
    private function getValue(string $field) {
        return $this->data[$field] ?? null;
    }
    
    /**
     * Validations spécifiques
     */
    private function validateRequired($value): bool {
        return !is_null($value) && trim((string)$value) !== '';
    }
    
    private function validateEmail($value): bool {
        return is_null($value) || filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    private function validateMin($value, int $min): bool {
        return is_null($value) || strlen((string)$value) >= $min;
    }
    
    private function validateMax($value, int $max): bool {
        return is_null($value) || strlen((string)$value) <= $max;
    }
    
    private function validateNumeric($value): bool {
        return is_null($value) || is_numeric($value);
    }
    
    private function validateInteger($value): bool {
        return is_null($value) || filter_var($value, FILTER_VALIDATE_INT) !== false;
    }
    
    private function validateAlpha($value): bool {
        return is_null($value) || preg_match('/^[a-zA-ZÀ-ÿ\s]+$/', $value);
    }
    
    private function validateAlphanumeric($value): bool {
        return is_null($value) || preg_match('/^[a-zA-Z0-9À-ÿ\s]+$/', $value);
    }
    
    private function validateRegex($value, string $pattern): bool {
        return is_null($value) || preg_match($pattern, $value);
    }
    
    private function validateSame($value, string $otherField): bool {
        return $value === $this->getValue($otherField);
    }
    
    private function validateDifferent($value, string $otherField): bool {
        return $value !== $this->getValue($otherField);
    }
    
    private function validateIn($value, array $allowedValues): bool {
        return is_null($value) || in_array($value, $allowedValues);
    }
    
    private function validateNotIn($value, array $forbiddenValues): bool {
        return is_null($value) || !in_array($value, $forbiddenValues);
    }
    
    private function validateUrl($value): bool {
        return is_null($value) || filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
    
    private function validateDate($value): bool {
        if (is_null($value)) return true;
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    }
    
    private function validateBoolean($value): bool {
        return is_null($value) || is_bool($value) || in_array($value, ['0', '1', 'true', 'false', true, false, 0, 1]);
    }
    
    private function validateStrongPassword($value): bool {
        if (is_null($value)) return true;
        
        return strlen($value) >= 8 &&
               preg_match('/[A-Z]/', $value) &&     // Au moins une majuscule
               preg_match('/[a-z]/', $value) &&     // Au moins une minuscule
               preg_match('/[0-9]/', $value) &&     // Au moins un chiffre
               preg_match('/[^a-zA-Z0-9]/', $value); // Au moins un caractère spécial
    }
    
    private function validateUsername($value): bool {
        if (is_null($value)) return true;
        
        // Username: 3-30 caractères, alphanumériques, underscore, point et tiret
        return preg_match('/^[a-zA-Z0-9._-]{3,30}$/', $value);
    }
    
    private function validateSafeText($value): bool {
        if (is_null($value)) return true;
        
        // Interdit les balises HTML et JavaScript
        $dangerous = ['<script', 'javascript:', 'on\w+\s*=', 'expression\s*\('];
        $valueToCheck = strtolower($value);
        
        foreach ($dangerous as $pattern) {
            if (preg_match('/' . $pattern . '/i', $valueToCheck)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Ajoute une erreur
     */
    private function addError(string $field, string $rule, array $params = []): void {
        $message = $this->getMessage($field, $rule, $params);
        
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        
        $this->errors[$field][] = $message;
    }
    
    /**
     * Génère le message d'erreur
     */
    private function getMessage(string $field, string $rule, array $params = []): string {
        $key = "$field.$rule";
        
        if (isset($this->messages[$key])) {
            $message = $this->messages[$key];
        } elseif (isset($this->messages[$rule])) {
            $message = $this->messages[$rule];
        } else {
            $message = self::$defaultMessages[$rule] ?? 'Le champ :field est invalide';
        }
        
        // Remplacer les placeholders
        $message = str_replace(':field', $this->getFieldName($field), $message);
        
        if (!empty($params)) {
            switch ($rule) {
                case 'min':
                    $message = str_replace(':min', $params[0], $message);
                    break;
                case 'max':
                    $message = str_replace(':max', $params[0], $message);
                    break;
                case 'same':
                case 'different':
                    $message = str_replace(':other', $this->getFieldName($params[0]), $message);
                    break;
                case 'in':
                case 'not_in':
                    $message = str_replace(':values', implode(', ', $params), $message);
                    break;
            }
        }
        
        return $message;
    }
    
    /**
     * Récupère le nom lisible du champ
     */
    private function getFieldName(string $field): string {
        $fieldNames = [
            'username' => 'nom d\'utilisateur',
            'password' => 'mot de passe',
            'confirm_password' => 'confirmation du mot de passe',
            'email' => 'email',
            'name' => 'nom',
            'role' => 'rôle',
            'csrf_token' => 'token CSRF'
        ];
        
        return $fieldNames[$field] ?? $field;
    }
    
    /**
     * Retourne les erreurs
     */
    public function errors(): array {
        return $this->errors;
    }
    
    /**
     * Retourne les erreurs formatées
     */
    public function getErrorsFlat(): array {
        $flat = [];
        foreach ($this->errors as $field => $fieldErrors) {
            $flat = array_merge($flat, $fieldErrors);
        }
        return $flat;
    }
    
    /**
     * Retourne les données validées
     */
    public function validated(): array {
        if (!empty($this->errors)) {
            throw new InvalidArgumentException('Validation failed');
        }
        
        $validated = [];
        foreach (array_keys($this->rules) as $field) {
            if (isset($this->data[$field])) {
                $validated[$field] = $this->data[$field];
            }
        }
        
        return $validated;
    }
    
    /**
     * Méthode statique de validation rapide
     */
    public static function make(array $data, array $rules, array $messages = []): self {
        $validator = new self($data);
        $validator->rules($rules)->messages($messages);
        return $validator;
    }
    
    /**
     * Validation spécifique pour les formulaires de connexion
     */
    public static function validateLogin(array $data): array {
        $validator = self::make($data, [
            'username' => 'required|min:3|max:50|username',
            'password' => 'required|min:1',
            'csrf_token' => 'required'
        ]);
        
        if (!$validator->validate()) {
            SecurityManager::logSecurityEvent('FORM_VALIDATION_FAILED', [
                'form' => 'login',
                'errors' => $validator->getErrorsFlat()
            ], 'MEDIUM');
            
            throw new InvalidArgumentException(implode(', ', $validator->getErrorsFlat()));
        }
        
        return $validator->validated();
    }
    
    /**
     * Validation spécifique pour la création d'utilisateurs
     */
    public static function validateUserCreation(array $data): array {
        $validator = self::make($data, [
            'username' => 'required|min:3|max:30|username',
            'password' => 'required|strong_password',
            'confirm_password' => 'required|same:password',
            'name' => 'required|min:2|max:100|safe_text',
            'email' => 'email|max:100',
            'role' => 'required|in:admin,user',
            'csrf_token' => 'required'
        ]);
        
        if (!$validator->validate()) {
            SecurityManager::logSecurityEvent('FORM_VALIDATION_FAILED', [
                'form' => 'user_creation',
                'errors' => $validator->getErrorsFlat()
            ], 'MEDIUM');
            
            throw new InvalidArgumentException(implode(', ', $validator->getErrorsFlat()));
        }
        
        return $validator->validated();
    }
    
    /**
     * Validation pour les paramètres d'API
     */
    public static function validateApiParams(array $data): array {
        $validator = self::make($data, [
            'annee' => 'required|integer|min:2000|max:2099',
            'periode' => 'required|safe_text|max:50',
            'zone' => 'required|safe_text|max:100'
        ]);
        
        if (!$validator->validate()) {
            SecurityManager::logSecurityEvent('API_VALIDATION_FAILED', [
                'params' => array_keys($data),
                'errors' => $validator->getErrorsFlat()
            ], 'MEDIUM');
            
            throw new InvalidArgumentException('Paramètres API invalides: ' . implode(', ', $validator->getErrorsFlat()));
        }
        
        return $validator->validated();
    }
}