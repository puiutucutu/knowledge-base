# Foreign Key Naming

Assuming `recipe` and `ingredient` tables with a `1:N` relationship between them.

## Dense

`FK_ChildTableName_ChildColName_ParentTableName_PrimaryKeyColName`

### Example

`FK_ingredient_recipeId_recipe_id`

### Dealing with Underscore Column Names

Use double underscores between.

`FK_ingredient__recipe_id__recipe__id`

## Current Preference

MSSQL allows using unicode symbol → (ALT+26).

`FK_{ingredient→recipe}{recipeId→id}`

## References

Using MSSQL AdventureWorks as a guide.

* <https://docs.microsoft.com/en-us/previous-versions/sql/sql-server-2008/ms124438(v=sql.100)>
* <https://stackoverflow.com/a/3593804/1727232>
