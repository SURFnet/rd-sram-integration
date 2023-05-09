# updateGroup()
```bash
curl --location --request PUT 'https://81-pondersource-devstock-tfcffv42pmm.ws-eu96b.gitpod.io/index.php/apps/federatedgroups/scim/Groups/federalists' \
--header 'Content-Type: application/json' \
--data-raw '{
    "members": [
        {
            "value": "fed_user_2@oc2.docker",
            "ref": "route to resource",
            "displayName": "fed_user_2"
        }
    ]
}'
```
Will return 
```json
{
    "members": [
        {
            "value": "fed_user_2@oc2.docker",
            "ref": "route to resource",
            "displayName": "fed_user_2"
        }
    ]
}
```

# getGroup($groupId)
```bash
curl --location 'https://81-pondersource-devstock-tfcffv42pmm.ws-eu96b.gitpod.io/index.php/apps/federatedgroups/scim/Groups/federalists'
```
Will return
```bash
{
	"totalResults": 0,
	"Resources": {
		"id": "id",
		"displayName": "displayName",
		"usersInGroup": "usersInGroup",
		"members": "members",
		"schemas": {},
		"meta": {
			"resourceType": "Group"
		},
		"urn:ietf:params:scim:schemas:cyberark:1.0:Group": []
	}
}
```