<?php

/*

vapi.php

Version 1.0.0 - 2024/06/07

A class I built that is designed to be inherited by other api units.
I wrote this so you can just call the class->process no matter the api
unit we are using.

This requires VCRUD for DB connectivity. This was built using VCRUD
version 1.2.0.  Also requires VTOKEN for token authentication management.
Build with VTOKEN version 1.0

functions:
    applog(message,[severity]): Adds a log message to the database, setting
        severity to 0 if not specified
    
    process(): This is the main function called. It is what is called by
        index.php to kick off whatever unit is being called. "unit" and
        "command" need to be set in the incoming HTTP request or we will
        fail out immediately.
    

See index.php for an example of how to use an inherited Vapi class.

*/

require_once('vcrud.php');

abstract class Vapi
{
    protected Vcrud $crud;
    protected string $unit;
    protected array $commands;
    protected array $data;
    protected ?int $userId;
    protected string $expiration = '+3 days';

    public function __construct(Vcrud $crud)
    {
        $this->crud = $crud;
        $this->unit = 'not assigned';
        $this->commands = [];
        $this->data = [];
        $this->userId = null;
    }

    public function applog(string $message, int $severity = 0): void
    {
        $this->crud->create('applog', [
            'eventTime' => date('Y-m-d H:i:s'),
            'unit' => $this->unit,
            'logString' => $message,
            'severity' => $severity
        ]);
    }

    public function setFields(string $command): bool
    {
        if (!array_key_exists($command, $this->commands)) {
            return false;
        }

        foreach ($this->commands[$command] as $field) {
            $fieldName = $field[0];
            $isRequired = $field[1];
            $fieldValue = $_REQUEST[$fieldName] ?? null;

            // Sanitize field value
            $sanitizedValue = htmlspecialchars($fieldValue);

            // Check if field is required
            if ($isRequired && !isset($_REQUEST[$fieldName])) {
                return false; // Return false if required field is missing
            }

            // Set field data
            if (isset($_REQUEST[$fieldName])) {
                $this->data[$fieldName] = $sanitizedValue;
            }
        }

        return true;
    }

    public function requiredFields(string $command): string
    {
        $requiredFields = array_filter($this->commands[$command], fn($field) => $field[1]);
        return implode(', ', array_column($requiredFields, 0));
    }

    public function process(): array
    {
        $_command = strtolower(htmlspecialchars($_REQUEST['command'] ?? ''));
        if ($_command === '') {
            return [
                'status' => 'error',
                'message' => "$this->unit: invalid command: $_command"
            ];
        }

        if (!$this->setFields($_command)) {
            return [
                'status' => 'error',
                'message' => "$this->unit: missing required fields: " . $this->requiredFields($_command)
            ];
        }

        if (isset($this->data['token'])) {
            $this->userId = $this->validateToken();
            if (empty($this->userId)) {
                return [
                    'status' => 'error',
                    'message' => 'system: invalid token'
                ];
            }
        }

        if (!array_key_exists($_command, $this->commands)) {
            return [
                'status' => 'error',
                'message' => "$this->unit: invalid command: $_command"
            ];
        }

        $function = 'do' . ucfirst($_command);
        return $this->$function();
    }

    protected function validateToken(): ?int
    {
        $token = new Vtoken($this->crud);
        $userId = $token->validateToken($this->data['token']);
        if ($userId) {
            $token->updateToken();
            $token->save();
            return $userId;
        }

        return null;
    }

}
