# Analysis Limitations and Statistical Pitfalls

This document explains the limitations of the current framework comparison metrics and why certain numbers can be misleading.

## Executive Summary

**Key Finding**: Average-based metrics are fundamentally flawed for framework comparison due to the "mass of simple code" effect.

**Most Reliable Metrics**:
1. ✅ Static analysis error density (PHPStan/Psalm errors per 1K LOC)
2. ✅ Maximum cognitive complexity (worst-case scenario)
3. ✅ Suppression count (technical debt indicator)

**Unreliable Metrics**:
1. ❌ Average method length
2. ❌ Average cognitive complexity
3. ❌ Complexity/LLOC (cyclomatic complexity per logical line)

## Problem 1: Average Method Length (Avg Method Length)

### The Numbers Say

| Framework | Avg Method Length (LLOC) |
|-----------|-------------------------|
| Laravel | 2.1 |
| BEAR.Sunday | 3.0 |
| Laminas | 3.6 |
| Symfony | 4.0 |
| CakePHP | 4.3 |

### The Reality

Laravel shows "2.1 lines per method", suggesting extremely short, simple methods. However, examining actual code reveals methods with 10-20+ lines.

**What's happening?**

```php
// These are counted as LLOC = 1
public function getKey() {
    return $this->key;  // 1 logical line
}

public function getRawPdo() {
    return $this->pdo;  // 1 logical line
}

// This is counted as LLOC = 3
public function parseKey(array $config) {
    if (Str::startsWith($key = $this->key($config), $prefix = 'base64:')) {  // 1
        $key = base64_decode(Str::after($key, $prefix));  // 2
    }
    return $key;  // 3
}
```

**Laravel has 3,710+ simple `return $this->property;` statements** out of 12,643 total methods.

### Why This Metric Fails

1. **Mass of simple code effect**: Thousands of 1-line getters/setters pull down the average
2. **LLOC vs physical lines**: 2.1 LLOC ≠ 2.1 visible lines (comments/whitespace excluded)
3. **Misleading interpretation**: Average suggests all code is simple, but business logic is complex
4. **Framework design bias**: Frameworks with many utility methods appear "simpler" than they are

### What You Should Look At Instead

- **Maximum method length**: Reveals the most complex method
- **Method length distribution**: P50, P75, P90, P95 percentiles
- **Cognitive complexity** (but see Problem 2)

---

## Problem 2: Average Cognitive Complexity

### The Numbers Say

| Framework | Avg Cognitive Complexity | Methods Analyzed |
|-----------|------------------------|------------------|
| BEAR.Sunday | 0.07 | 949 |
| Laravel | 0.08 | 11,911 |
| Symfony | 0.19 | 42,062 |
| Laminas | 0.24 | 3,948 |

### The Reality

Laravel and BEAR.Sunday appear nearly identical (0.07 vs 0.08), suggesting similar code complexity.

**Actual distribution in Laravel:**

```json
{
  "methods": [
    {"name": "response", "score": 0},
    {"name": "setResponse", "score": 0},
    {"name": "withStatus", "score": 0},
    {"name": "asNotFound", "score": 0},
    {"name": "status", "score": 0},
    // ... thousands more with score 0
    {"name": "resolveAuthCallback", "score": 1.905},
    {"name": "getPolicyFor", "score": 1.856}
  ]
}
```

**Most methods have score 0** (simple getters, setters, proxies).

### Why This Metric Fails

1. **Same issue as method length**: Mass of zero-complexity methods
2. **Average doesn't show distribution**: No information about how many complex methods exist
3. **Median is also unreliable**: Would likely be 0 for Laravel
4. **Percentiles needed**: P90-P99 would reveal true complexity

### What You Should Look At Instead

✅ **Maximum cognitive complexity** - This is reliable:

| Framework | Max Cognitive Complexity |
|-----------|------------------------|
| BEAR.Sunday | 3.243 | ← Even worst method is simple
| Laravel | 7.233 |
| CakePHP | 8.385 |
| Yii2 | 8.225 |
| Laminas | 11.273 |
| CodeIgniter | 11.814 |
| Symfony | 14.617 | ← Contains very complex methods

**Key insight**: BEAR.Sunday's maximum of 3.243 means even its most complex method is relatively simple. Symfony's 14.617 indicates at least one extremely complex method exists.

---

## Problem 3: Complexity/LLOC

### The Numbers Say

| Framework | Complexity/LLOC |
|-----------|----------------|
| Symfony | 0.23 | ← Lowest
| BEAR.Sunday | 0.27 |
| Laravel | 0.38 |
| Laminas | 0.39 |

### Why This Is Misleading

Symfony's 1.9M LOC includes:
- Thousands of DTO classes with only getters/setters
- Hundreds of exception classes (complexity = 1)
- Many interface implementations (thin wrappers)
- Auto-generated code

**Example:**
```php
class MissingAppKeyException extends RuntimeException
{
    public function __construct($message = '...') {
        parent::__construct($message);  // Complexity = 1, LLOC = 1
    }
}
```

Thousands of such classes bring the average down.

### What You Should Look At Instead

- Complexity/LLOC **combined with** cognitive complexity
- Not as a standalone metric

---

## What's Missing: Type Coverage

### The Critical Gap

**Type Coverage** = Percentage of code with type annotations

```php
// Type Coverage: 0% (no types)
function process($data) {
    return $data->value;
}

// Type Coverage: 100% (fully typed)
function process(Request $data): Response {
    return new Response($data->value);
}
```

### Why Type Coverage Matters

1. **Not affected by "mass of simple code"**
   - A getter without types still counts as untyped
   - Ratio-based metric resists statistical distortion

2. **Predicts future quality**
   - High type coverage → Safe refactoring
   - Low type coverage → Hidden bugs

3. **More meaningful than error counts**
   - 0 errors could mean: perfect types OR no types (can't check)
   - Type coverage reveals the difference

### Expected Results (Estimated)

Based on static analysis errors:

```
Symfony:     85-90%  (2 PHPStan errors, comprehensive typing)
BEAR.Sunday: 80-85%  (0 Psalm errors, good typing)
CakePHP:     75-80%  (18 PHPStan errors)
Laravel:     40-50%  (11,792 PHPStan errors suggests poor typing)
Laminas:     30-40%  (Legacy codebase)
Yii2:        30-40%  (Legacy codebase)
```

**This would be the most reliable quality metric.**

---

## Reliable Metrics in Current Report

### 1. Static Analysis Error Density

**PHPStan (Level 8) Errors per 1K LOC:**

```
Symfony:      0.00  ← Excellent
CakePHP:      0.12
BEAR.Sunday:  1.92
CodeIgniter: 19.91
Laminas:     30.77
Yii2:        38.50
Laravel:     48.60  ← Problematic
```

**Why reliable:**
- Objective measurement
- Not affected by code volume tricks
- Directly measures type safety

**Caveat**: Different tools show different results (PHPStan vs Psalm)

### 2. Maximum Cognitive Complexity

Shows the **worst-case scenario** - most difficult code to maintain.

```
BEAR.Sunday:   3.243  ← Consistently simple
Laravel:       7.233
Symfony:      14.617  ← Contains very complex code
```

### 3. Suppression Count (Technical Debt)

| Framework | Total Suppressions | Baseline |
|-----------|-------------------|----------|
| Symfony | 0 | 0 | ← No hidden issues
| Laravel | 9 | 0 |
| BEAR.Sunday | 136 | 0 |
| CakePHP | 208 | 124 |
| Laminas | 134 | 2,841 | ← Heavy technical debt

**High baseline = problems swept under the rug**

---

## Recommendations

### For Framework Comparison

**DO use:**
1. ✅ Static analysis error density (errors/1K LOC)
2. ✅ Maximum cognitive complexity
3. ✅ Suppression and baseline counts
4. ✅ Type coverage (when available)

**DO NOT use:**
1. ❌ Average method length
2. ❌ Average cognitive complexity
3. ❌ Complexity/LLOC as standalone

**Better alternatives:**
- Percentile distributions (P50, P75, P90, P95, P99)
- Complexity buckets (how many methods in each range)
- Type coverage percentage

### For This Project

**Proposed improvements:**

1. **Add Type Coverage analysis**
   - Use Psalm's `--show-info=true`
   - Or `tomasvotruba/type-coverage`

2. **Add distribution metrics**
   - Cognitive complexity percentiles
   - Method length percentiles
   - Complexity buckets

3. **Clarify existing metrics**
   - Rename "Avg Method Length" to "Avg LLOC per Method (excluding comments/whitespace)"
   - Add warnings about interpretation

4. **Focus on actionable insights**
   - Which frameworks prioritize type safety?
   - Which have technical debt (suppressions)?
   - Which have consistently simple code (max complexity)?

---

## Conclusion

**The fundamental problem**: Average-based metrics are dominated by the "mass of simple code" effect.

**The solution**:
- Use maximum values (worst-case)
- Use error densities (objective)
- Add type coverage (missing crucial metric)
- Provide distributions, not just averages

**Current report verdict**:
- ✅ Good starting point
- ❌ Misleading averages
- ⚠️  Missing type coverage
- 📊 Needs distribution data

**Remember**: *"All models are wrong, but some are useful."* Use these metrics as rough indicators, not absolute truth. **Read the actual code** to understand quality.

---

## References

- [Cognitive Complexity Whitepaper](https://www.sonarsource.com/resources/white-papers/cognitive-complexity/)
- [Cyclomatic Complexity](https://en.wikipedia.org/wiki/Cyclomatic_complexity)
- [PHPStan Documentation](https://phpstan.org/)
- [Psalm Documentation](https://psalm.dev/)
- Statistical pitfall: [Simpson's Paradox](https://en.wikipedia.org/wiki/Simpson%27s_paradox)
