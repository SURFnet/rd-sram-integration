# Authorization
there is a token that is stored in **appconfig** table with key `scim_token` and appid `federatedgroups` it should be set to a random generated string. 

You can also set it using
```
insert into oc_appconfig (appid, configkey, configvalue) VALUES ('federatedgroups', 'scim_token', 'something-super-secret');
```

please ask the adminstrator to share that token with you and then send requests with the below header: 

> "x-auth: Bearer <SCIM_TOKEN>"


# getGroups 
Will return all groups in OwnCloud

```bash
curl --location '/index.php/apps/federatedgroups/scim/Groups'
```

RESPONSE STATUS 200
```json
{
    "status": "succes",
    "message": "",
    "data": {
        "totalResults": 3,
        "Resources": [
            {
                "id": "admin",
                "displayName": "admin",
                "members": [
                    {
                        "value": "einstein",
                        "ref": "",
                        "displayName": ""
                    }
                ]
            },
            {
                "id": "federalists",
                "displayName": "federalists",
                "members": []
            },
            {
                "id": "customgroup_Custard with Mustard",
                "displayName": "Custard with Mustard",
                "members": [
                    {
                        "value": "einstein",
                        "ref": "",
                        "displayName": ""
                    },
                    {
                        "value": "marie#oc2.docker",
                        "ref": "",
                        "displayName": ""
                    }
                ]
            }
        ]
    }
}
```

# getGroup($groupId)

```bash
curl --location '/index.php/apps/federatedgroups/scim/Groups/federalists'
```

RESPONSE STATUS 200
```json
{
    "status": "succes",
    "message": "Succesfully deleted group: test_g",
    "data": {
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
}
```

# deleteGroup($groupId)

```bash
curl --location --request DELETE '/index.php/apps/federatedgroups/scim/Groups/federalists'
```
RESPONSE STATUS 204
```json
{
    "status": "succes",
    "message": "Succesfully deleted group: test_g",
    "data": null
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
    "status": "succes",
    "message": "",
    "data": {
        "members": [
            {
                "value": "fed_user_2@oc2.docker",
                "ref": "",
                "displayName": ""
            }
        ]
    }
}

```

# createGroup

```bash
curl --location --request POST '/index.php/apps/federatedgroups/scim/Groups'
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
    "status":  "succes"
    "message": ""
    "data":{
        "id": "federalists",
        "members": [
            {
                "value": "fed_user_2@oc2.docker",
                "ref": "",
                "displayName": ""
            }
        ]
    }
}
```
