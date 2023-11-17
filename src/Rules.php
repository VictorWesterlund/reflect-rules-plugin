<?php

	namespace ReflectRules;

	use \victorwesterlund\xEnum;

	// Supported types for is_type()
	enum Type {
		use xEnum;
		
		case NUMBER;
		case STRING;
		case BOOLEAN;
		case ARRAY;
		case OBJECT;
		case NULL;
	}

	class Rules {
		private string $property;

		public bool $required = false;
		public ?Type $type = null;

		public ?int $min = null;
		public ?int $max = null;

		public function __construct(string $property) {
			$this->property = $property;
		}

		public function get_property_name(): string {
			return $this->property;
		}

		/*
			# Constraints
			Chain these methods to create rules for a particular property.
			When all rules are defiend, the eval_* methods will be called
		*/

		// A sequential array of additional Rule instances for a
		private function object_rules(array $rules): self {
			$this->object_rules = $rules;
			return $this;
		}

		// Set the minimum lenth/size for property
		public function min(?int $value = null) {
			$this->min = $value;
			return $this;
		}

		// Set the maximum length/size for property
		public function max(?int $value = null) {
			$this->max = $value;
			return $this;
		}

		// This property has to exist in scope
		public function required(bool $flag = true): self {
			$this->required = $flag;
			return $this;
		}

		// Set property Type
		public function type(Type|string $type): self {
			// Coerce string to Type enum
			if (!($type instanceof Type)) {
				$type = Type::fromName($string);
			}

			$this->type = $type;
			return $this;
		}

		/*
			# Eval methods
			These methods are used to check conformity against set rules.
			Methods are not called until all rules have been defined.
		*/

		public function eval_required(array $scope): bool {
			return array_key_exists($this->property, $scope);
		}

		public function eval_type(mixed $value): bool {
			return match($this->type) {
				Type::NUMBER  => is_numeric($value),
				Type::STRING  => is_string($value),
				Type::BOOLEAN => is_bool($value),
				Type::ARRAY   => is_array($value),
				Type::OBJECT  => $this->eval_object($value),
				Type::NULL    => is_null($value),
				default => true
			};
		}

		public function eval_min(mixed $value): bool {
			return match($this->type) {
				Type::NUMBER => $this->eval_type($value) && $value >= $this->min,
				Type::STRING => $this->eval_type($value) && strlen($value) >= $this->min,
				Type::ARRAY,
				Type::OBJECT => $this->eval_type($value) && count($value) >= $this->min,
				default => true
			};
		}

		public function eval_max(mixed $value): bool {
			return match($this->type) {
				Type::NUMBER => $this->eval_type($value) && $value <= $this->max,
				Type::STRING => $this->eval_type($value) && strlen($value) <= $this->max,
				Type::ARRAY,
				Type::OBJECT => $this->eval_type($value) && count($value) <= $this->max,
				default => true
			};
		}

		// TODO: Recursive Rules eval of multidimensional object
		public function eval_object(mixed $object): bool {
			return is_array($object);
		}
	}