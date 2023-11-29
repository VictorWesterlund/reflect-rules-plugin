<?php

	namespace ReflectRules;

	use \victorwesterlund\xEnum;

	use \ReflectRules\Scope;

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

		// Matched Type against $types array
		private ?Type $type = null;
		// Typed array of type ReflectRules\Type
		public ?array $types = null;

		private bool $default_enabled = false;
		public mixed $default;

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

		// Set property Types
		public function type(Type $type): self {
			$this->types[] = $type;
			return $this;
		}

		// Set a default value if property is not provided
		public function default(mixed $value): self {
			$this->default_enabled = true;
			$this->default = $value;
			
			return $this;
		}

		/*
			# Eval methods
			These methods are used to check conformity against set rules.
			Methods are not called until all rules have been defined.
		*/

		private function eval_type_boolean(mixed $value, Scope $scope): bool {
			// Coerce $value to bool primitive from string for GET parameters
			if ($scope === Scope::GET) {
				switch ($value) {
					case "true":
					case "1":
					case "on":
					case "yes":
						$value = true;
						break;
	
					case "false":
					case "0":
					case "off":
					case "no":
						$value = false;
						break;
	
					default:
						$value = null;
				}

				// Mutate value on superglobal from string to primitive
				$GLOBALS[$scope->value][$this->property] = $value;
			}

			return is_bool($value);
		}

		/*
			## Public eval methods
			These are the entry-point eval methods that in turn can call other
			helper methods for fine-graned validation.
		*/

		public function eval_required(Scope $scope): bool {
			$scope_data = &$GLOBALS[$scope->value];

			if (array_key_exists($this->property, $scope_data)) {
				return true;
			}

			// Property does not exist in superglobal, create one with default value if enabled
			if ($this->default_enabled) {
				$scope_data[$this->property] = $this->default;
			}

			return false;
		}

		public function eval_type(mixed $value, Scope $scope): bool {
			$match = false;

			foreach ($this->types as $type) {
				match($type) {
					Type::NUMBER  => $match = is_numeric($value),
					Type::STRING  => $match = is_string($value),
					Type::BOOLEAN => $match = $this->eval_type_boolean($value, $scope),
					Type::ARRAY,
					Type::OBJECT  => $match = is_array($value),
					Type::NULL    => $match = is_null($value)
				};

				// Found a matching type
				if ($match) {
					// Set the matched Type for use in other rules
					$this->type = $type;
					return true;
				}
			}

			// No matching types were found
			return false;
		}

		public function eval_min(mixed $value, Scope $scope): bool {
			return match($this->type) {
				Type::NUMBER => $this->eval_type($value, $scope) && $value >= $this->min,
				Type::STRING => $this->eval_type($value, $scope) && strlen($value) >= $this->min,
				Type::ARRAY,
				Type::OBJECT => $this->eval_type($value, $scope) && count($value) >= $this->min,
				default => true
			};
		}

		public function eval_max(mixed $value, Scope $scope): bool {
			return match($this->type) {
				Type::NUMBER => $this->eval_type($value, $scope) && $value <= $this->max,
				Type::STRING => $this->eval_type($value, $scope) && strlen($value) <= $this->max,
				Type::ARRAY,
				Type::OBJECT => $this->eval_type($value, $scope) && count($value) <= $this->max,
				default => true
			};
		}
	}