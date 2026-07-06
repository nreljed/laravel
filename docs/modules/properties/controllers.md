# Properties Module — Controllers

| Controller | File | Purpose |
|------------|------|---------|
| `PropertyController` | `Controllers/PropertyController.php` | CRUD, terms, images, status |

## PropertyController

Routes under `/dashboard/properties`:

| Method | Route | Purpose | Access |
|--------|-------|---------|--------|
| `index()` | `GET /` | List properties | `properties.view` |
| `create()` | `GET /create` | Show create form | `properties.create` |
| `store()` | `POST /` | Create property | `properties.create` |
| `edit()` | `GET /{property}/edit` | Show edit form | `properties.edit` |
| `update()` | `PUT /{property}` | Update property | `properties.edit` |
| `destroy()` | `DELETE /{property}` | Delete property | `properties.delete` |
| `updateStatus()` | `PATCH /{property}/status` | Update status | `properties.edit` |
| `bulkStatus()` | `POST /bulk/status` | Bulk status change | `properties.edit` |
| `uploadImages()` | `POST /{property}/images` | Upload images | `properties.edit` |
| `deleteImage()` | `DELETE /{property}/images/{image}` | Delete image | `properties.edit` |
| `reorderImages()` | `POST /{property}/images/reorder` | Reorder images | `properties.edit` |
| `setFeaturedImage()` | `POST /{property}/images/{image}/featured` | Set featured | `properties.edit` |

### Term Management (same controller)

| Method | Route | Purpose |
|--------|-------|---------|
| `types()` | `GET /types` | List property types |
| `storeType()` | `POST /types` | Create type |
| `updateType()` | `PUT /types/{type}` | Update type |
| `destroyType()` | `DELETE /types/{type}` | Delete type |
| `cities()` | `GET /cities` | List cities |
| `storeCity()` | `POST /cities` | Create city |
| `updateCity()` | `PUT /cities/{city}` | Update city |
| `destroyCity()` | `DELETE /cities/{city}` | Delete city |
| `features()` | `GET /features` | List features |
| `storeFeature()` | `POST /features` | Create feature |
| `updateFeature()` | `PUT /features/{feature}` | Update feature |
| `destroyFeature()` | `DELETE /features/{feature}` | Delete feature |
| `statuses()` | `GET /statuses` | List property statuses |
| `storeStatus()` | `POST /statuses` | Create status |
| `updateStatus()` | `PUT /statuses/{status}` | Update status |
| `destroyStatus()` | `DELETE /statuses/{status}` | Delete status |
| `reorderStatuses()` | `POST /statuses/reorder` | Reorder statuses |
