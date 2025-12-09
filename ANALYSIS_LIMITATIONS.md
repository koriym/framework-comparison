# Analysis Limitations and Statistical Pitfalls

This document explains the limitations of the current framework comparison metrics and why certain numbers can be misleading.

## Executive Summary

**Key Finding**: Nearly all single-value metrics are fundamentally flawed for framework comparison.

**Why Metrics Fail**:
- **Averages** → Distorted by mass of simple code (getters, setters, DTOs)
- **Maximums** → Distorted by single outliers
- **Medians** → Same issue as averages

**Only Relatively Reliable Metric**:
1. ✅ Static analysis error density (PHPStan/Psalm errors per 1K LOC)
   - Evaluates entire codebase
   - Not easily gamed by code structure

**Problematic Metrics**:
1. ❌ Average method length - Mass of 1-line getters
2. ❌ Average cognitive complexity - Mass of zero-complexity methods
3. ❌ Maximum complexity - Single outlier effect
4. ❌ Complexity/LLOC - Distorted by DTOs/exceptions

**What's Missing**:
- ⚠️ Type Coverage (most important)
- ⚠️ Percentile distributions (P50, P75, P90, P95, P99)
- ⚠️ Bucket distributions (how many methods in each complexity range)

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

⚠️ **No single metric is reliable.** All alternatives have their own problems:

- **Maximum method length** → Single outlier effect (see Problem 4)
- **Method length distribution (P50-P95)** → Still distorted by mass of simple code
- **Cognitive complexity** → Same issue (see Problem 2)

**The only solution**: Bucket distribution (count of methods in each length range)

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

⚠️ **Maximum cognitive complexity** - Better than average, but still problematic:

| Framework | Max Cognitive Complexity |
|-----------|------------------------|
| BEAR.Sunday | 3.243 |
| Laravel | 7.233 |
| CakePHP | 8.385 |
| Yii2 | 8.225 |
| Laminas | 11.273 |
| CodeIgniter | 11.814 |
| Symfony | 14.617 |

**Why this is also misleading:**

Symfony's 14.617 could mean:
- **Scenario A**: 1 extremely complex method out of 45,867 (0.002% - not a real problem)
- **Scenario B**: 500 methods with complexity 10-15 (1% - serious problem)

**You cannot tell which scenario is true from the maximum value alone.**

**What we really need**: P95, P99, and bucket distribution:

```
Methods with complexity > 10:
  BEAR.Sunday:  0 methods (0%)      ← Consistently simple
  Laravel:     23 methods (0.19%)   ← Few complex methods
  Symfony:    456 methods (1.08%)   ← Many complex methods
```

This would reveal whether high complexity is an outlier or a systemic issue.

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

**Type Coverage would be the most reliable quality metric** - but it is currently missing from this analysis.

---

## Problem 4: Maximum Values (Outlier Effect)

### The Pitfall

**A single outlier distorts the entire picture.**

| Framework | Max Cognitive Complexity | Total Methods |
|-----------|------------------------|---------------|
| Symfony | 14.617 | 45,867 |
| BEAR.Sunday | 3.243 | 1,085 |

### The Question

Does Symfony's 14.617 mean:
- **1 outlier** among 45,867 methods? (99.998% are fine)
- **Many complex methods** with the worst being 14.617? (systemic issue)

**We cannot tell from the maximum alone.**

### Why This Matters

If it's **1 outlier**:
- Not a real quality problem
- Just one old/legacy method
- Overall codebase is clean

If it's **many complex methods**:
- Serious maintainability issue
- Difficult to refactor
- High bug risk

### What's Needed

**Percentiles and bucket distribution:**

```
P95 (95th percentile):
  - Ignores the top 5% outliers
  - Shows typical "complex" code

Bucket counts:
  Complexity 10+:
    - Symfony: 456 methods (1.08%) → systemic issue
    - Laravel: 23 methods (0.19%) → few outliers
    - BEAR.Sunday: 0 methods (0%) → consistently simple
```

This reveals whether high complexity is exceptional or endemic.

---

## The Fundamental Problem

### All Single-Value Metrics Are Unreliable

| Metric | Distortion |
|--------|-----------|
| **Average** | Pulled down by mass of simple code |
| **Median** | Same issue as average |
| **Maximum** | Pulled up by single outlier |
| **Minimum** | Always near zero (meaningless) |

**Truth**: You need the **full distribution** to understand quality.

---

## Relatively Reliable Metrics in Current Report

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

### 2. Maximum Cognitive Complexity (with caveats)

Shows the **worst-case scenario** - but may be just one outlier (see Problem 4).

```
BEAR.Sunday:   3.243  ← Consistently simple (low max = no outliers)
Laravel:       7.233
Symfony:      14.617  ← Could be 1 outlier or systemic issue
```

⚠️ **Low maximum is meaningful** (no complex code exists), but **high maximum alone is inconclusive** (need bucket distribution to know if systemic).

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
1. ✅ Static analysis error density (errors/1K LOC) - Most reliable
2. ✅ Suppression and baseline counts - Shows technical debt
3. ✅ Type coverage (when available) - Would be most important

**USE WITH EXTREME CAUTION:**
1. ⚠️ Maximum cognitive complexity - Check if it's an outlier or systemic
2. ⚠️ Average metrics - Only with distribution data

**DO NOT use alone:**
1. ❌ Average method length - Meaningless without distribution
2. ❌ Average cognitive complexity - Meaningless without distribution
3. ❌ Maximum values - Meaningless without context (percentiles)
4. ❌ Complexity/LLOC - Too easily distorted

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
