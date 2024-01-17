<?php

	namespace ReflectRules;

	use \ReflectRules\Rules;

	require_once "Rules.php";

	// Available superglobal scopes
	enum Scope: string {
		case GET  = "_GET";
		case POST = "_POST";

		static function get_array(): array {
			return [
				Scope::GET->name  => [],
				Scope::POST->name => []
			];
		}
	}

	enum Error {
		case VALUE_MIN_ERROR;
		case VALUE_MAX_ERROR;
		case UNKNOWN_PROPERTY_NAME;
		case INVALID_PROPERTY_TYPE;
		case INVALID_PROPERTY_VALUE;
		case MISSING_REQUIRED_PROPERTY;
	}

	class Ruleset {
		private bool $is_valid = true;
		private ?bool $strict;
		private array $errors;

		private array $rules_names;

		public function __construct(bool $strict = false) {
			/*
				Strict mode can only be enabled or disabled as a bool argument.
				'null' is used internally on this property as a re-evaluate flag.
			*/
			$this->strict = $strict;

			$this->errors = Scope::get_array();
			$this->rules_names = Scope::get_array();
		}

		// Append an error to the array of errors
		private function add_error(Error $error, Scope $scope, string $property, mixed $expected): void {
			// Create sub array if this is the first error for this property
			if (!array_key_exists($property, $this->errors[$scope->name])) {
				$this->errors[$scope->name][$property] = [];
			}

			// Set expected value value for property in scope
			$this->errors[$scope->name][$property][$error->name] = $expected;
			// Unset valid flag
			$this->is_valid = false;
		}

		// Evaluate an array of Rules property names against scope keys
		private function eval_strict(Scope $scope): void {
			$name_diffs = array_diff(array_keys($GLOBALS[$scope->value]), $this->rules_names[$scope->name]);

			// Set errors for each undefined property
			foreach ($name_diffs as $name_diff) {
				$this->add_error(Error::UNKNOWN_PROPERTY_NAME, $scope, $name_diff, null);
			}

			// Unset strict mode property now that we have evaled it up to this point
			$this->strict = null;
		}

		// Evaluate Rules against a given value
		private function eval_rules(Rules $rules, Scope $scope): void {
			// Get the name of the current property being evaluated
			$name = $rules->get_property_name();

			// Check if property name exists in scope
			if (!$rules->eval_required($scope)) {
				// Don't perform further processing if the property is optional and not provided
				if (!$rules->required) {
					return;
				}

				$this->add_error(Error::MISSING_REQUIRED_PROPERTY, $scope, $name, $name);
				return;
			}

			// Get value from scope for the current property
			$value = $GLOBALS[$scope->value][$name];

			/*
				Eval each rule that has been set.
				The error messages will be returned 
			*/

			// Value is not of the correct type or enum value
			if ($rules->types && !$rules->eval_type($value, $scope)) {
				if (!$rules->enum) {
					// Get type names from enum
					$types = array_map(fn(Type $type): string => $type->name, $rules->types);

					$this->add_error(Error::INVALID_PROPERTY_TYPE, $scope, $name, $types);
				} else {
					$this->add_error(Error::INVALID_PROPERTY_VALUE, $scope, $name, $rules->enum);
				}
			}

			if ($rules->min && !$rules->eval_min($value, $scope)) {
				$this->add_error(Error::VALUE_MIN_ERROR, $scope, $name, $rules->min);
			}

			if ($rules->max && !$rules->eval_max($value, $scope)) {
				$this->add_error(Error::VALUE_MAX_ERROR, $scope, $name, $rules->max);
			}
		}

		// ----

		// Perform request processing on GET properties (search parameters)
		public function GET(array $rules): void {
			// (Re)enable strict mode if property is null
			if ($this->strict === null) {
				$this->strict = true;
			}

			foreach ($rules as $rule) {
				$this->rules_names[Scope::GET->name][] = $rule->get_property_name();

				$this->eval_rules($rule, Scope::GET);
			}
		}

		// Perform request processing on POST properties (request body)
		public function POST(array $rules): void {
			// (Re)enable strict mode if property is null
			if ($this->strict === null) {
				$this->strict = true;
			}

			foreach ($rules as $rule) {
				$this->rules_names[Scope::POST->name][] = $rule->get_property_name();

				$this->eval_rules($rule, Scope::POST);
			}
		}

		// ----

		// Return array of all set Errors
		public function get_errors(): array {
			// Strict mode is enabled
			if ($this->strict === true) {
				$this->eval_strict(Scope::GET);
				$this->eval_strict(Scope::POST);
			}

			return $this->errors;
		}

		public function is_valid(): bool {
			// Strict mode is enabled
			if ($this->strict === true) {
				$this->eval_strict(Scope::GET);
				$this->eval_strict(Scope::POST);
			}

			return $this->is_valid;
		}
	}