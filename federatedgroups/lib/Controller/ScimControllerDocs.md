# getGroups

```bash
curl --location '/index.php/apps/federatedgroups/scim/Groups'
```

RESPONSE:
```json
{
    "totalResults": 0,
    "Resources": {
        "groups": [
            "admin",
            "federalists",
            "test_g",
            "customgroup_Custard with Mustard"
        ],
        "assignableGroups": [
            "admin",
            "federalists",
            "test_g"
        ],
        "removableGroups": [
            "admin",
            "federalists",
            "test_g"
        ]
    }
}
```

# getGroup($groupId)

```bash
curl --location '/index.php/apps/federatedgroups/scim/Groups/federalists'
```

RESPONSE
```json
{
    "totalResults": 0,
    "Resources": {
        "id": "federalists",
        "displayName": "federalists",
        "usersInGroup": [
            "fed_user_2#oc2.docker"
        ],
        "members": [
            {
                "value": "fed_user_2#oc2.docker",
                "ref": "fed_user_2#oc2.docker",
                "displayName": "fed_user_2#oc2.docker"
            }
        ],
        "schemas": [],
        "meta": {
            "resourceType": "Group"
        },
        "urn:ietf:params:scim:schemas:cyberark:1.0:Group": []
    }
}
```

# deleteGroup($groupId)

```bash
curl --location --request DELETE '/index.php/apps/federatedgroups/scim/Groups/federalists'
```
RESPONSE
```json
{
    "status": "success",
    "data": {
        "message": "Succesfully deleted group: test_g"
    }
}
```
# updateGroup($groupId)

```bash
curl --location --request PUT '/index.php/apps/federatedgroups/scim/Groups/federalists'
```
BODY
```bash
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
RESPONSE
```json
{
    "status": "success",
    "data": {
        "message": "Succesfully deleted group: test_g"
    }
}
```

# createGroup

```bash
curl --location --request PUT '/index.php/apps/federatedgroups/scim/Groups'
```
BODY
```bash
{
    "id": "federalists",
    "members": [
        {
            "value": "fed_user_2@oc2.docker",
            "ref": "route to resource",
            "displayName": "fed_user_2"
        }
    ]
}
```
RESPONSE
```json
{
    "status": "success",
    "data": {
        "message": "Succesfully deleted group: test_g"
    }
}