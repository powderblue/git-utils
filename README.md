# Git Utils

Simple tools for working with Git in PHP.

## Class `Gitignore`

Use to add patterns to a gitignore file.

Among others, provides methods to insert/append one/more patterns.

## @todo

- Sanity-check the append, and the insert, logic.
- Either: extract file-handling from `Gitignore`; or do not automatically mutate the loaded file.
