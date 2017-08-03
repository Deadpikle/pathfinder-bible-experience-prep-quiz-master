<?php
	class Test {
		private $passed = 0;
		private $errors = [];

		public function equal($left, $right) {
			if ($left == $right) {
				$this->passed++;
			} else {
				$this->errors[] = [$left, 'should equal', $right];
			}
		}

		public function print_results() {
			$failed = count($this->errors);
			$assertions = $this->passed + $failed;

			echo "$assertions run\n";
			echo "\t$this->passed passed\n";
			echo "\t$failed failed\n";

			if ($failed > 0) {
				var_dump($this->errors);
			}
		}
	}
?>
