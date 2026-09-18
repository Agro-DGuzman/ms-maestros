// Autoriza a la identidad a bajar imágenes del registro.
//
// Va en un módulo aparte porque la asignación de rol tiene que crearse en el
// grupo de recursos del registro, que puede no ser el mismo donde viven las
// aplicaciones. Un módulo es la forma que tiene Bicep de cambiar de alcance.

param nombreDelRegistro string
param idDeLaIdentidad string
param principalDeLaIdentidad string
param rolDeLectura string

resource registro 'Microsoft.ContainerRegistry/registries@2023-07-01' existing = {
  name: nombreDelRegistro
}

resource permiso 'Microsoft.Authorization/roleAssignments@2022-04-01' = {
  // El nombre de una asignación es un GUID y tiene que ser estable: derivarlo
  // de las tres partes hace que volver a desplegar actualice la misma en vez
  // de intentar crear otra y fallar porque ya existe.
  name: guid(registro.id, idDeLaIdentidad, rolDeLectura)
  scope: registro
  properties: {
    roleDefinitionId: rolDeLectura
    principalId: principalDeLaIdentidad
    // Sin esto, una identidad recién creada puede no estar replicada todavía y
    // la asignación falla diciendo que el principal no existe.
    principalType: 'ServicePrincipal'
  }
}
