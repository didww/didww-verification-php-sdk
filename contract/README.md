# contract/

`wire_contract.json` is a snapshot of the API this SDK speaks to: its routes, its
vocabularies, and the rules the SDK cannot discover at runtime. `capturedAt` says when it
was taken.

It makes drift **detectable**, and only by comparison against the service: nothing in this
repository can tell you whether the SDK still matches it. The test suite asserts that the
SDK's exported vocabularies equal this file's. That catches a vocabulary edit that forgets
the snapshot, but not the two being wrong together. For that, re-capture the snapshot from
the service at release time and read the diff.

Re-capture it from the service, never from another client library. A client library is
itself an earlier snapshot, so seeding from one silently imports the drift it has
accumulated, dated as if it were current.
