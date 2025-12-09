# Analysis Limitations: Why Average-Based Metrics Are Traps

## The Problem

**All average-based metrics are distorted by the "mass of simple code" effect.**

Example: Laravel shows "Avg Method Length 2.1 lines" and "Avg Cognitive Complexity 0.08"

Reality:
- 97.4% of methods (11,599 / 11,911) have complexity 0-1
- ~3,700+ one-line `return $this->property;` statements
- P90 complexity is just 0.182

**The mass of simple getters/setters pulls down all averages.**

## What Doesn't Work

| Metric | Problem |
|--------|---------|
| **Average** | Distorted by mass of simple code |
| **Median** | Same issue (most methods are simple) |
| **Maximum** | Single outlier effect - can't tell if systemic |
| **P90/P95** | Still distorted (Laravel P90 = 0.182) |

## What Works

| Metric | Why |
|--------|-----|
| **Static analysis error density** | Evaluates entire codebase objectively |
| **Suppression counts** | Shows hidden technical debt |
| **Low maximum complexity** | Proves no complex code exists |
| **Bucket distribution** | Shows how many methods are actually complex |

## Example: Bucket Distribution

This reveals the truth that averages hide:

```
Laravel (11,911 methods):
  Complexity 0-1:   11,599 (97.4%)  ← mass of simple code
  Complexity 1-3:      275 (2.3%)
  Complexity 3-5:       32 (0.3%)
  Complexity 5-10:       5 (0.04%)
  Complexity 10+:        0 (0%)    ← no complex methods
```

## Conclusion

**Don't trust averages. Look at distributions.**

The only reliable metrics:
1. Static analysis error density (errors/1K LOC)
2. Bucket distributions (count per complexity range)
3. Type coverage (currently missing from this analysis)

---

*"All models are wrong, but some are useful."* — George Box
