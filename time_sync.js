
document.addEventListener('DOMContentLoaded', () => {
  const clientData = {
    clientTime: Math.floor(Date.now() / 1000),
    timezoneOffset: new Date().getTimezoneOffset()
  };
  
  localStorage.setItem('clientTime', clientData.clientTime);
  localStorage.setItem('timezoneOffset', clientData.timezoneOffset);
  
  fetch('update_time.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams(clientData)
  })
  .then(response => response.ok && console.log('Time synchronized'))
  .catch(error => console.error('Time sync error:', error));
});
