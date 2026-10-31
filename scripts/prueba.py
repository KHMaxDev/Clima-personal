import openmeteo_requests
import pandas as pd
import requests_cache
from retry_requests import retry

# Configuración del cliente de Open-Meteo con caché y reintentos
cache_session = requests_cache.CachedSession('.cache', expire_after=3600)
retry_session = retry(cache_session, retries=5, backoff_factor=0.2)
openmeteo = openmeteo_requests.Client(session=retry_session)

# --- CONFIGURACIÓN DE ZONAS ---
# Descomenta la zona que deseas probar y asegúrate de comentar las demás.

# 1. Chetumal, Quintana Roo (Activa por defecto)
latitud = 18.5196
longitud = -88.3040
nombre_ciudad = "Chetumal, Quintana Roo"

# 2. Cancún, Quintana Roo
# latitud = 21.1619
# longitud = -86.8515
# nombre_ciudad = "Cancún, Quintana Roo"

# 3. Ciudad de México (CDMX)
# latitud = 19.4326
# longitud = -99.1332
# nombre_ciudad = "Ciudad de México (CDMX)"

# --- PETICIÓN A LA API ---
url = "https://api.open-meteo.com/v1/forecast"
params = {
    "latitude": latitud,
    "longitude": longitud,
    "hourly": [
        "temperature_2m", 
        "weather_code", 
        "wind_speed_10m", 
        "vapour_pressure_deficit", 
        "uv_index", 
        "uv_index_clear_sky", 
        "cloud_cover", 
        "shortwave_radiation"
    ],
}
responses = openmeteo.weather_api(url, params=params)

# --- PROCESAMIENTO DE DATOS ---
response = responses[0]

# Textos de salida mejorados y más entendibles
print(f"=== REPORTE METEOROLÓGICO: {nombre_ciudad} ===")
print(f"📍 Coordenadas: Latitud {response.Latitude():.4f}° | Longitud {response.Longitude():.4f}°")
print(f"⛰️ Elevación: {response.Elevation()} m sobre el nivel del mar")
print(f"🕒 Zona horaria (Offset GMT): {response.UtcOffsetSeconds() / 3600} horas\n")

# Extraer las variables solicitadas (El orden es el mismo que en los 'params')
hourly = response.Hourly()
hourly_temperature_2m = hourly.Variables(0).ValuesAsNumpy()
hourly_weather_code = hourly.Variables(1).ValuesAsNumpy()
hourly_wind_speed_10m = hourly.Variables(2).ValuesAsNumpy()
hourly_vapour_pressure_deficit = hourly.Variables(3).ValuesAsNumpy()
hourly_uv_index = hourly.Variables(4).ValuesAsNumpy()
hourly_uv_index_clear_sky = hourly.Variables(5).ValuesAsNumpy()
hourly_cloud_cover = hourly.Variables(6).ValuesAsNumpy()
hourly_shortwave_radiation = hourly.Variables(7).ValuesAsNumpy()

# Crear un diccionario con nombres de columnas claros en español
hourly_data = {
    "Fecha y Hora (UTC)": pd.date_range(
        start=pd.to_datetime(hourly.Time(), unit="s", utc=True),
        end=pd.to_datetime(hourly.TimeEnd(), unit="s", utc=True),
        freq=pd.Timedelta(seconds=hourly.Interval()),
        inclusive="left"
    )
}

# Asignar los datos extraídos a las columnas
hourly_data["Temperatura (°C)"] = hourly_temperature_2m
hourly_data["Código de Clima"] = hourly_weather_code
hourly_data["Velocidad Viento (km/h)"] = hourly_wind_speed_10m
hourly_data["Déficit Presión Vapor (kPa)"] = hourly_vapour_pressure_deficit
hourly_data["Índice UV"] = hourly_uv_index
hourly_data["Índice UV (Despejado)"] = hourly_uv_index_clear_sky
hourly_data["Cobertura Nubosa (%)"] = hourly_cloud_cover
hourly_data["Radiación Onda Corta (W/m²)"] = hourly_shortwave_radiation

# Construir el DataFrame
hourly_dataframe = pd.DataFrame(data=hourly_data)

print("--- Pronóstico por Hora (Mostrando las primeras 24 horas) ---")
# Usamos .head(24) para no saturar la consola, si quieres ver todo cambia a print(hourly_dataframe)
print(hourly_dataframe.head(24).to_string())