<?php

namespace ContentEgg\application\BlockKit;

defined('\ABSPATH') || exit;

/**
 * ValidationResult class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

final class ValidationResult
{
    public $valid = true;
    public $errors = array();
    public $warnings = array();

    /**
     * Normalized tree of the VALID nodes only. Complete (usable for
     * serialization) only when $valid === true.
     */
    public $tree = array();

    public function error(string $path, string $code, string $message): void
    {
        $this->valid = false;
        $this->errors[] = array('path' => $path, 'code' => $code, 'message' => $message);
    }

    public function warn(string $path, string $code, string $message): void
    {
        $this->warnings[] = array('path' => $path, 'code' => $code, 'message' => $message);
    }

    public function toArray(): array
    {
        return array('valid' => $this->valid, 'errors' => $this->errors, 'warnings' => $this->warnings);
    }
}
