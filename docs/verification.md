# Verification

## Commands
- `verify:counts`
- `verify:relations`
- `verify:integrity`
- `verify:files`

## Relation checks
- task → user
- deal → company
- deal → contact
- comment → entity
- file references and path metadata

`verify:files` validates metadata/reference integrity only; heavy payload transfer is out of runtime scope.
