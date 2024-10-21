document.addEventListener('DOMContentLoaded', () => {
  const districtSelect = document.getElementById('district');
  const divisionSelect = document.getElementById('division');

  const divisions = {
    kandy: ['Udunuwara', 'Yatinuwara'],
    colombo: ['Siduva', 'Negambo']
  };

  districtSelect.addEventListener('change', () => {
    const selectedDistrict = districtSelect.value;
    // Clear previous options
    divisionSelect.innerHTML = '<option value="">Select Division</option>';

    if (selectedDistrict && divisions[selectedDistrict]) {
      divisions[selectedDistrict].forEach(division => {
        const option = document.createElement('option');
        option.value = division.toLowerCase().replace(/\s+/g, '-'); // Convert to a valid option value
        option.textContent = division;
        divisionSelect.appendChild(option);
      });
    }
  });
});
