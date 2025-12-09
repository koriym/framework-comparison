# PHP Framework Comparison Results

Generated: 2025-12-09 01:44:28

## Code Metrics (phploc)

| Framework | LOC | Classes | Methods | Avg Method Length | Complexity/LLOC |
|-----------|-----|---------|---------|-------------------|----------------|
| BEAR.Sunday | 21,910 | 389 | 1,085 | 3 | 0.27 |
| CakePHP | 148,259 | 525 | 4,981 | 4.3 | 0.45 |
| CodeIgniter | 117,117 | 459 | 4,045 | 4.4 | 0.48 |
| Laminas | 101,391 | 745 | 4,276 | 3.6 | 0.39 |
| Laravel | 242,642 | 1,077 | 12,643 | 2.1 | 0.38 |
| Symfony | 1,885,272 | 7,908 | 45,867 | 4 | 0.23 |
| Yii2 | 116,714 | 402 | 3,477 | 4.5 | 0.48 |

## Cognitive Complexity

| Framework | Methods | Avg Complexity | Max Complexity |
|-----------|---------|----------------|----------------|
| BEAR.Sunday | 949 | 0.07 | 3.243 |
| CakePHP | 4,640 | 0.33 | 8.385 |
| CodeIgniter | 3,679 | 0.38 | 11.814 |
| Laminas | 3,948 | 0.24 | 11.273 |
| Laravel | 11,911 | 0.08 | 7.233 |
| Symfony | 42,062 | 0.19 | 14.617 |
| Yii2 | 3,313 | 0.48 | 8.225 |

## Static Analysis Errors

| Framework | PHPStan | /1K LOC | Psalm | /1K LOC |
|-----------|---------|---------|-------|---------|
| BEAR.Sunday | 42 | 1.92 | 0 | 0 |
| CakePHP | 18 | 0.12 | 3027 | 20.42 |
| CodeIgniter | 2332 | 19.91 | 46 | 0.39 |
| Laminas | 3120 | 30.77 | 6175 | 60.9 |
| Laravel | 11792 | 48.6 | 8157 | 33.62 |
| Symfony | 2 | 0 | 55425 | 29.4 |
| Yii2 | 4494 | 38.5 | 3211 | 27.51 |

## Silenced Issues (Inline Annotations & Baselines)

| Framework | @phpstan-ignore | @psalm-suppress | phpcs:ignore | @codeCoverageIgnore | PHPStan Baseline | Psalm Baseline |
|-----------|-----------------|-----------------|--------------|---------------------|------------------|----------------|
| BEAR.Sunday | 34 | 102 | 22 | 79 | 0 | 0 |
| CakePHP | 17 | 0 | 67 | 0 | 124 | 0 |
| CodeIgniter | 12 | 25 | 0 | 225 | 0 | 87 |
| Laminas | 0 | 37 | 97 | 0 | 0 | 2841 |
| Laravel | 9 | 0 | 0 | 0 | 0 | 0 |
| Symfony | 0 | 0 | 0 | 0 | 0 | 0 |
| Yii2 | 0 | 0 | 18 | 0 | 91 | 0 |

## Frameworks Analyzed

| Framework | Version | PHP | Analysis Time | First Release | GitHub |
|-----------|---------|-----|---------------|---------------|--------|
| BEAR.Sunday | unknown | `8.2+` | 9s | 2015 | [bearsunday/BEAR.Sunday](https://github.com/bearsunday/BEAR.Sunday) |
| CakePHP | 5.2.10 | `>=8.1` | 20s | 2005 | [cakephp/cakephp](https://github.com/cakephp/cakephp) |
| CodeIgniter | 4.6.3 | `8.1+` | 24s | 2006 | [codeigniter4/CodeIgniter4](https://github.com/codeigniter4/CodeIgniter4) |
| Laminas | 3.9.x | - | 1m 3s | 2006 | [laminas/laminas-mvc](https://github.com/laminas/laminas-mvc) |
| Laravel | 12.41.1 | `8.2+` | 52s | 2011 | [laravel/framework](https://github.com/laravel/framework) |
| Symfony | 8.0.2-DEV | `>=8.4` | 6m 58s | 2005 | [symfony/symfony](https://github.com/symfony/symfony) |
| Yii2 | 2.0.54-dev | `>=7.4` | 21s | 2008 | [yiisoft/yii2](https://github.com/yiisoft/yii2) |

## Notes

- PHPStan and Psalm run at their strictest levels
- Silenced issues = errors hidden via inline annotations or baseline files
- Lower error counts indicate better type safety and static analysis compliance
- BEAR.Sunday: analyzed from BEAR.Package vendor/bear/* and vendor/ray/* packages (core framework only, excluding optional bridge modules)
- Laminas: analyzed 10 core packages (mvc, db, view, form, validator, router, servicemanager, eventmanager, http, session)
- Symfony: analyzed per-component using root autoloader (Psalm: 67 components, Cognitive: 66/67)
- LOC = Lines of Code, LLOC = Logical Lines of Code
- Complexity/LLOC = Cyclomatic complexity per logical line of code
