# opencloudmesh app for ownCloud version 10

In ownCloud version 10, the OpenCloudMesh protocol is partially implemented. This app, combined with
our "ocm-cleaning" branch of owncloud/core, completes the implementation, so that not only user-to-user
but also user-to-group sharing becomes possible.

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
* `./scripts/init-gitpod-prebuild.sh`
*`./scripts/init-opencloudmesh.sh`
* `./scripts/testing-opencloudmesh.sh`.

Then:
* open the browser-in-a-browser that will be started on port 5800
* log in to https://oc2.docker as marie / radioactivity
* create a group called 'scientists'
* log in to https://oc1.docker as einstein / relativity
* share something with scientists@oc2.docker
* log in to https://oc2.docker as marie / radioactivity
* accept the share from einstein
