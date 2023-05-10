# getGroups

```bash
curl --location '/index.php/apps/federatedgroups/scim/Groups'
```

RESPONSE STATUS 200
```json
{
    "totalResults": 0,
    "Resources": [
            "admin",
            "federalists",
            "test_g",
            "customgroup_Custard with Mustard"
        ]
}
```

# getGroup($groupId)

```bash
curl --location '/index.php/apps/federatedgroups/scim/Groups/federalists'
```

RESPONSE STATUS 200
```json
{
    "id": "federalists",
    "displayName": "federalists",
    "members": [
        {
            "value": "fed_user_2#oc2.docker",
            "ref": "",
            "displayName": ""
        }
    ],
    "schemas": [],
    "meta": {
        "resourceType": "Group"
    },
    "urn:ietf:params:scim:schemas:cyberark:1.0:Group": []
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
            "ref": "",
            "displayName": ""
        }
    ]
}
```
RESPONSE STATUS: 200
```json
{
    "members": [
        {
            "value": "fed_user_2@oc2.docker",
            "ref": "",
            "displayName": ""
        }
    ]
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
            "ref": "",
            "displayName": ""
        }
    ]
}
```
RESPONSE STATUS: 201
```json
{
    "id": "federalists",
    "members": [
        {
            "value": "fed_user_2@oc2.docker",
            "ref": "",
            "displayName": ""
        }
    ]
}
```