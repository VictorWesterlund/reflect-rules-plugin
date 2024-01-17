<?php

	namespace ReflectRules;

	use \ReflectRules\Scope;

	// Supported types for is_type()
	enum Type {
		case NULL;
		case ENUM;
		case ARRAY;
		case NUMBER;
		case STRING;
		case OBJECT;
		case BOOLEAN;
	}

	class Rules {
		private const CSV_DELIMITER = ",";

		private string $property;

		/*
			# Rule properties
			These properties store rules for an instance of a property
		*/

		public bool $required = false;

		// Matched Type against $types array
		private ?Type $type = null;
		// Typed array of type ReflectRules\Type
		public ?array $types = null;

		public ?array $enum = null;

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
		public function min(?int $value = null): self {
			$this->min = $value;
			return $this;
		}

		// Set the maximum length/size for property
		public function max(?int $value = null): self {
			$this->max = $value;
			return $this;
		}

		// This property has to exist in scope
		public function required(bool $flag = true): self {
			$this->required = $flag;
			return $this;
		}

		// Add Type constraint with optional argument
		public function type(Type $type, mixed $arg = null): self {
			if ($type === Type::ENUM) {
				if (!is_array($arg)) {
					throw new \Exception("Expected type 'array' of ENUM values as second argument");
				}

				$this->enum = $arg;
			}

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

		private function eval_type_enum(mixed $value): bool {
			// Return true if value isn't boolean and exists in enum array
			return !is_bool($value) && in_array($value, $this->enum);
		}

		private function eval_object(mixed $value, Scope $scope): bool {
			// Arrays in POST parameters should already be decoded
			if ($scope === Scope::POST) {
				return is_array($value);
			}

			// Decode stringified JSON
			$json = json_decode($value);

			// Failed to decode JSON
			if ($json === null) {
				return false;
			}

			// Mutate property on superglobal with decoded JSON
			$GLOBALS[Scope::GET->value][$this->property] = $json;

			return true;
		}

		private function eval_array(string|array $value, Scope $scope): bool {
			// Arrays in POST parameters should already be decoded
			if ($scope === Scope::POST) {
				return is_array($value);
			}

			// Mutate property on superglobal with decoded CSV if not already an array
			if (!is_array($_GET[$this->property])) {
				$GLOBALS[Scope::GET->value][$this->property] = explode(self::CSV_DELIMITER, $_GET[$this->property]);
			}

			return true;
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
					Type::ARRAY   => $match = $this->eval_array($value, $scope),
					Type::OBJECT  => $match = $this->eval_object($value, $scope),
					Type::ENUM    => $match = $this->eval_type_enum($value),
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
				Type::OBJECT => $this->eval_type($value, $scope) && count($GLOBALS[$scope->value][$this->property]) >= $this->min,
				default => true
			};
		}

		public function eval_max(mixed $value, Scope $scope): bool {
			return match($this->type) {
				Type::NUMBER => $this->eval_type($value, $scope) && $value <= $this->max,
				Type::STRING => $this->eval_type($value, $scope) && strlen($value) <= $this->max,
				Type::ARRAY,
				Type::OBJECT => $this->eval_type($value, $scope) && count($GLOBALS[$scope->value][$this->property]) <= $this->max,
				default => true
			};
		}
	}