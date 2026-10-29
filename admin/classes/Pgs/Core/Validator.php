<?php

declare(strict_types=1);

namespace Pgs\Core;

use Pgs\Core\Exceptions\ValidationException;

final class Validator
{
    /** @var array<string,mixed> */
    private array $clean = [];
    /** @var array<string,string> */
    private array $errors = [];

    /**
     * @param array<string,mixed> $input
     */
    private function __construct(private array $input)
    {
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function make(array $input): self
    {
        return new self($input);
    }

    private function raw(string $field): mixed
    {
        $v = $this->input[$field] ?? null;
        return is_string($v) ? trim($v) : $v;
    }

    private function missing(mixed $v): bool
    {
        return $v === null || $v === '';
    }

    public function string(string $field, bool $required = true, int $min = 0, int $max = 255): self
    {
        $v = $this->raw($field);
        if ($this->missing($v)) {
            return $this->finish($field, null, $required);
        }
        if (!is_string($v) && !is_int($v)) {
            return $this->fail($field, 'Invalid text');
        }
        $v = (string) $v;
        $len = mb_strlen($v);
        if ($len < $min || $len > $max) {
            return $this->fail($field, "Length must be between $min and $max");
        }
        $this->clean[$field] = $v;
        return $this;
    }

    public function int(string $field, bool $required = true, ?int $min = null, ?int $max = null): self
    {
        $v = $this->raw($field);
        if ($this->missing($v)) {
            return $this->finish($field, null, $required);
        }
        if (is_string($v) && preg_match('/^-?\d+$/', $v)) {
            $v = (int) $v;
        }
        if (!is_int($v) || ($min !== null && $v < $min) || ($max !== null && $v > $max)) {
            return $this->fail($field, 'Invalid number');
        }
        $this->clean[$field] = $v;
        return $this;
    }

    public function decimal(string $field, bool $required = true, int $decimals = 2): self
    {
        $v = $this->raw($field);
        if ($this->missing($v)) {
            return $this->finish($field, null, $required);
        }
        try {
            $this->clean[$field] = Money::normalize($v, $decimals);
        } catch (ValidationException $e) {
            return $this->fail($field, 'Invalid amount');
        }
        return $this;
    }

    /**
     * @param list<string> $allowed
     */
    public function enum(string $field, array $allowed, bool $required = true): self
    {
        $v = $this->raw($field);
        if ($this->missing($v)) {
            return $this->finish($field, null, $required);
        }
        if (!is_string($v) || !in_array($v, $allowed, true)) {
            return $this->fail($field, 'Invalid value');
        }
        $this->clean[$field] = $v;
        return $this;
    }

    public function date(string $field, bool $required = true): self
    {
        $v = $this->raw($field);
        if ($this->missing($v)) {
            return $this->finish($field, null, $required);
        }
        $d = is_string($v) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $v) : false;
        if ($d === false || $d->format('Y-m-d') !== $v) {
            return $this->fail($field, 'Invalid date');
        }
        $this->clean[$field] = $v;
        return $this;
    }

    public function email(string $field, bool $required = true): self
    {
        $v = $this->raw($field);
        if ($this->missing($v)) {
            return $this->finish($field, null, $required);
        }
        if (!is_string($v) || mb_strlen($v) > 150 || filter_var($v, FILTER_VALIDATE_EMAIL) === false) {
            return $this->fail($field, 'Invalid email');
        }
        $this->clean[$field] = $v;
        return $this;
    }

    public function phone(string $field, bool $required = true): self
    {
        $v = $this->raw($field);
        if ($this->missing($v)) {
            return $this->finish($field, null, $required);
        }
        $e164 = is_string($v) ? Phone::toE164($v) : null;
        if ($e164 === null) {
            return $this->fail($field, 'Invalid phone number');
        }
        $this->clean[$field] = $e164;
        return $this;
    }

    public function bool(string $field, bool $default = false): self
    {
        $v = $this->input[$field] ?? $default;
        $this->clean[$field] = filter_var($v, FILTER_VALIDATE_BOOLEAN);
        return $this;
    }

    /**
     * @return array<string,mixed>
     */
    public function validate(): array
    {
        if ($this->errors !== []) {
            throw new ValidationException($this->errors);
        }
        return $this->clean;
    }

    private function finish(string $field, mixed $value, bool $required): self
    {
        if ($required) {
            return $this->fail($field, 'Required');
        }
        $this->clean[$field] = $value;
        return $this;
    }

    private function fail(string $field, string $message): self
    {
        $this->errors[$field] = $message;
        return $this;
    }
}
