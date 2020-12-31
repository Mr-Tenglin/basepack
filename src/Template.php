<?php
namespace tenglin\basepack;

use Exception;

class Template {
	public $view_path = './';
	public $view_suffix = '.html';
	public $tpl_begin = '{';
	public $tpl_end = '}';
	private $assign = [];

	public function __construct(array $view = null, array $tpl = null) {
		if (!empty($view['path'])) {
			$this->view_path = $view['path'];
		}
		if (!empty($view['suffix'])) {
			$this->view_suffix = $view['suffix'];
		}
		if (!empty($tpl['begin'])) {
			$this->tpl_begin = $tpl['begin'];
		}
		if (!empty($tpl['end'])) {
			$this->tpl_end = $tpl['end'];
		}
		return $this;
	}

	public function assign($key, $value) {
		$this->assign[$key] = $value;
		return $this;
	}

	public function display($template, array $assign = null) {
		if (!empty($assign)) {
			foreach ($assign as $key => $value) {
				$this->assign[$key] = $value;
			}
		}
		return $this->compile($this->view_path . $template . $this->view_suffix);
	}

	private function compile($file) {
		if (is_file($file)) {
			$string = file_get_contents($file);
			if (preg_match_all('#' . $this->tpl_begin . 'include\s+file=["|\'](.+)["|\']' . $this->tpl_end . '#U', $string, $matches)) {
				for ($i = 0; $i < count($matches[0]); $i++) {
					$string = str_replace($matches[0][$i], file_get_contents($this->view_path . $matches[1][$i] . $this->view_suffix), $string);
				}
			}
			return $this->parse($string);
		} else {
			throw new Exception('Missing template file ' . $file);
		}
	}

	private function parse($string) {
		$keys = [
			'if %%' => '<?php if (\1): ?>',
			'elseif %%' => '<?php ; elseif (\1): ?>',
			'else' => '<?php ; else: ?>',
			'/if' => '<?php endif; ?>',
			'for %%' => '<?php for (\1): ?>',
			'/for' => '<?php endfor; ?>',
			'foreach %%' => '<?php foreach (\1): ?>',
			'/foreach' => '<?php endforeach; ?>',
			'while %%' => '<?php while (\1): ?>',
			'/while' => '<?php endwhile; ?>',
			'continue' => '<?php continue; ?>',
			'break' => '<?php break; ?>',
			'$%% = %%' => '<?php $\1 = \2; ?>',
			'$%%++' => '<?php $\1++; ?>',
			'$%%--' => '<?php $\1--; ?>',
			'$%%' => '<?php echo $\1; ?>',
			'php' => '<?php /*',
			'/php' => '*/ ?>',
			'/*' => '<?php /*',
			'*/' => '*/ ?>',
			':%%' => '<?php \1; ?>',
		];

		foreach ($keys as $key => $val) {
			$patterns[] = '#' . str_replace('%%', '(.+)', preg_quote($this->tpl_begin . $key . $this->tpl_end, '#')) . '#U';
			$replace[] = $val;
		}
		$template = preg_replace($patterns, $replace, $string);
		return $this->evaluate($template, $this->assign);
	}

	private function evaluate($code, array $variables = NULL) {
		if ($variables != NULL) {
			extract($variables);
		}
		return eval('?>' . $code);
	}
}
