# opencloudmesh app for ownCloud version 10

In OwnCloud version 10, the OpenCloudMesh protocol is partially implemented. This app is our solution to complete the implementation so by using this app, not only user-to-user
but also user-to-group federated sharing becomes possible.

therefore in the pure Owncloud installation without any custom application, you just can share the file with a federated user by typing `user-name@domain-of-another-owncloud`
but, when you will install the openCloudMesh Application you can also share the file with a remote group by typing `group-name@domain-of-another-owncloud.

So when you are typing inside the sharee search textbox after that you add the `@` the result should be like the below image: 
![image](https://github.com/pondersource/oc-opencloudmesh/assets/123634558/3ea154a3-d2e0-49cb-9366-6c871ebfafcb)

and when you click the second item in the result list (item with group icon) your file will be shared with all the members of the group on the remote server. (the group and its users should be present on the remote server)


## Status
This app is still in alpha testing, so don't be surprised if it doesn't work for you yet!

## Usage
This app requires you to run [this branch of ownCloud](https://github.com/pondersource/core/tree/accept-ocm-to-groups)
(sorry, this will become easier once the PR is merged!) and add the following line to your config.php:
```php
  'sharing.managerFactory' => 'OCA\\OpenCloudMesh\\ShareProviderFactory',
  'sharing.remoteShareesSearch' => 'OCA\\OpenCloudMesh\\ShareeSearchPlugin',
  'sharing.ocmController' => 'OCA\\OpenCloudMesh\\Controller\\OcmController',
  'sharing.groupExternalManager' => 'OCA\\OpenCloudMesh\\GroupExternalManager',


```

## Development
To debug it, you can open https://github.com/pondersource/dev-stock
on GitPod and run:

* `./scripts/init-opencloudmesh.sh`
* `./scripts/testing-opencloudmesh.sh`.

Then:
* Open the browser-in-a-browser that will be started on port 5800
* log in to https://oc2.docker as marie / radioactivity
* create a group called 'scientists'
* log in to https://oc1.docker as einstein / relativity
* create a *folder* and share it with scientists@oc2.docker (careful, sharing welcome.txt will not work!)
* log in to https://oc2.docker as marie / radioactivity
* accept the share from einstein
