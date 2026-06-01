<?php
// Form Validation Class
class Validator {
    private $errors = [];
    private $data = [];
    private $fieldNames = [];

    public function __construct($data, $rules = []) {
        $this->data = $data;
        if (!empty($rules)) {
            $this->validate($rules);
        }
    }

    public function validate($rules) {
        foreach ($rules as $field => $ruleSet) {
            if (is_string($ruleSet)) {
                $ruleSet = explode('|', $ruleSet);
            }
            $value = $this->data[$field] ?? null;
            
            foreach ($ruleSet as $rule) {
                $params = [];
                if (strpos($rule, ':') !== false) {
                    list($rule, $paramStr) = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }
                
                $methodName = 'rule' . ucfirst($rule);
                if (method_exists($this, $methodName)) {
                    $this->$methodName($field, $value, $params);
                }
            }
        }
        return $this;
    }

    public function passes() {
        return empty($this->errors);
    }

    public function fails() {
        return !empty($this->errors);
    }

    public function errors() {
        return $this->errors;
    }

    public function firstError() {
        return !empty($this->errors) ? reset($this->errors)[0] : null;
    }

    public function allErrors() {
        $all = [];
        foreach ($this->errors as $field => $errs) {
            foreach ($errs as $err) {
                $all[] = $err;
            }
        }
        return $all;
    }

    public function getValidated() {
        $validated = [];
        $fieldKeys = array_keys($this->errors ?? []);
        foreach ($this->data as $key => $value) {
            if (!in_array($key, $fieldKeys)) {
                $validated[$key] = $value;
            }
        }
        return $validated;
    }

    // Custom field name
    public function setFieldName($field, $name) {
        $this->fieldNames[$field] = $name;
        return $this;
    }

    public function setFieldNames($names) {
        $this->fieldNames = array_merge($this->fieldNames, $names);
        return $this;
    }

    private function getFieldName($field) {
        return $this->fieldNames[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    // Validation Rules
    private function ruleRequired($field, $value, $params) {
        if (empty($value) && $value !== '0' && $value !== 0) {
            $this->addError($field, $this->getFieldName($field) . ' is required');
        }
    }

    private function ruleEmail($field, $value, $params) {
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, $this->getFieldName($field) . ' must be a valid email');
        }
    }

    private function ruleMin($field, $value, $params) {
        $min = (int)$params[0];
        if (!empty($value) && strlen($value) < $min) {
            $this->addError($field, $this->getFieldName($field) . ' must be at least ' . $min . ' characters');
        }
    }

    private function ruleMax($field, $value, $params) {
        $max = (int)$params[0];
        if (!empty($value) && strlen($value) > $max) {
            $this->addError($field, $this->getFieldName($field) . ' must not exceed ' . $max . ' characters');
        }
    }

    private function ruleNumeric($field, $value, $params) {
        if (!empty($value) && !is_numeric($value)) {
            $this->addError($field, $this->getFieldName($field) . ' must be a number');
        }
    }

    private function ruleInteger($field, $value, $params) {
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
            $this->addError($field, $this->getFieldName($field) . ' must be an integer');
        }
    }

    private function ruleAlpha($field, $value, $params) {
        if (!empty($value) && !ctype_alpha(str_replace(' ', '', $value))) {
            $this->addError($field, $this->getFieldName($field) . ' must contain only letters');
        }
    }

    private function ruleAlphanumeric($field, $value, $params) {
        if (!empty($value) && !ctype_alnum(str_replace(' ', '', $value))) {
            $this->addError($field, $this->getFieldName($field) . ' must contain only letters and numbers');
        }
    }

    private function ruleRegex($field, $value, $params) {
        if (!empty($value) && !preg_match($params[0], $value)) {
            $this->addError($field, $this->getFieldName($field) . ' format is invalid');
        }
    }

    private function ruleUrl($field, $value, $params) {
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, $this->getFieldName($field) . ' must be a valid URL');
        }
    }

    private function rulePhone($field, $value, $params) {
        if (!empty($value) && !preg_match('/^[0-9\-\(\)\+\.\s]{7,15}$/', $value)) {
            $this->addError($field, $this->getFieldName($field) . ' must be a valid phone number');
        }
    }

    private function ruleMobile($field, $value, $params) {
        if (!empty($value) && !preg_match('/^(98|97|96)\d{8}$/', $value)) {
            $this->addError($field, $this->getFieldName($field) . ' must be a valid Nepali mobile number');
        }
    }

    private function ruleDate($field, $value, $params) {
        if (!empty($value) && !strtotime($value)) {
            $this->addError($field, $this->getFieldName($field) . ' must be a valid date');
        }
    }

    private function ruleAfter($field, $value, $params) {
        if (!empty($value) && strtotime($value) <= strtotime($params[0])) {
            $this->addError($field, $this->getFieldName($field) . ' must be after ' . $params[0]);
        }
    }

    private function ruleBefore($field, $value, $params) {
        if (!empty($value) && strtotime($value) >= strtotime($params[0])) {
            $this->addError($field, $this->getFieldName($field) . ' must be before ' . $params[0]);
        }
    }

    private function ruleIn($field, $value, $params) {
        if (!empty($value) && !in_array($value, $params)) {
            $this->addError($field, $this->getFieldName($field) . ' must be one of: ' . implode(', ', $params));
        }
    }

    private function ruleNotIn($field, $value, $params) {
        if (!empty($value) && in_array($value, $params)) {
            $this->addError($field, $this->getFieldName($field) . ' is not allowed');
        }
    }

    private function ruleConfirmed($field, $value, $params) {
        $confirmationField = $field . '_confirmation';
        $confirmationValue = $this->data[$confirmationField] ?? null;
        if ($value !== $confirmationValue) {
            $this->addError($field, $this->getFieldName($field) . ' confirmation does not match');
        }
    }

    private function ruleUnique($field, $value, $params) {
        $table = $params[0];
        $column = $params[1] ?? $field;
        $ignoreId = $params[2] ?? null;
        $ignoreColumn = $params[3] ?? 'id';
        
        if (!empty($value)) {
            $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = :value AND deleted_at IS NULL";
            $bind = ['value' => $value];
            if ($ignoreId) {
                $sql .= " AND {$ignoreColumn} != :ignore_id";
                $bind['ignore_id'] = $ignoreId;
            }
            if (db()->fetchColumn($sql, $bind) > 0) {
                $this->addError($field, $this->getFieldName($field) . ' already exists');
            }
        }
    }

    private function ruleExists($field, $value, $params) {
        $table = $params[0];
        $column = $params[1] ?? $field;
        if (!empty($value)) {
            if (!db()->exists($table, "{$column} = :value", ['value' => $value])) {
                $this->addError($field, $this->getFieldName($field) . ' does not exist');
            }
        }
    }

    private function ruleFile($field, $value, $params) {
        if (empty($value['name'])) return;
        $allowedTypes = $params ?? ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        $ext = strtolower(pathinfo($value['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedTypes)) {
            $this->addError($field, $this->getFieldName($field) . ' must be a file of type: ' . implode(', ', $allowedTypes));
        }
        if ($value['size'] > MAX_FILE_SIZE) {
            $this->addError($field, $this->getFieldName($field) . ' must be under ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB');
        }
    }

    private function ruleBoolean($field, $value, $params) {
        if ($value !== null && !in_array($value, [true, false, 0, 1, '0', '1'], true)) {
            $this->addError($field, $this->getFieldName($field) . ' must be boolean');
        }
    }

    private function ruleArray($field, $value, $params) {
        if ($value !== null && !is_array($value)) {
            $this->addError($field, $this->getFieldName($field) . ' must be an array');
        }
    }
}

// Helper function
function validator($data, $rules = []) {
    return new Validator($data, $rules);
}
