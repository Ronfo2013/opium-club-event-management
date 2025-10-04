import React, { useState, useEffect } from 'react';
import { getImageUrl } from '../../utils/imageUtils';

const EventCarousel = ({ events }) => {
  const [currentEvent, setCurrentEvent] = useState(0);

  useEffect(() => {
    if (events.length > 1) {
      const interval = setInterval(() => {
        setCurrentEvent((prev) => (prev + 1) % events.length);
      }, 5000);
      return () => clearInterval(interval);
    }
  }, [events.length]);

  if (!events || events.length === 0) {
    return (
      <section className="relative h-96 bg-gradient-to-r from-purple-600 to-blue-600">
        <div className="absolute inset-0 bg-black bg-opacity-50"></div>
        <div className="relative h-full flex items-center justify-center">
          <div className="text-center text-white">
            <h1 className="text-4xl md:text-6xl font-bold mb-4">Opium Club</h1>
            <p className="text-xl md:text-2xl">Pordenone</p>
            <p className="text-lg mt-2">Sistema di gestione eventi con QR Code</p>
          </div>
        </div>
      </section>
    );
  }

  const event = events[currentEvent];

  return (
    <section className="relative h-96 overflow-hidden">
      {event.background_image && (
        <div 
          className="absolute inset-0 bg-cover bg-center bg-no-repeat"
          style={{
            backgroundImage: `url(${getImageUrl(event.background_image)})`
          }}
        />
      )}
      <div className="absolute inset-0 bg-black bg-opacity-20"></div>
      
      <div className="relative h-full flex items-center justify-center">
        <div className="text-center text-white px-4">
          <h1 className="text-4xl md:text-6xl font-bold mb-4">Opium Club</h1>
          <p className="text-xl md:text-2xl mb-2">Pordenone</p>
          <h2 className="text-2xl md:text-3xl font-semibold mb-4">
            {event.titolo}
          </h2>
          <p className="text-lg mb-4">
            {new Date(event.event_date).toLocaleDateString('it-IT', {
              weekday: 'long',
              year: 'numeric',
              month: 'long',
              day: 'numeric'
            })}
          </p>
          {event.chiuso ? (
            <span className="inline-block bg-red-600 text-white px-4 py-2 rounded-full text-sm font-medium">
              Evento Chiuso
            </span>
          ) : (
            <span className="inline-block bg-green-600 text-white px-4 py-2 rounded-full text-sm font-medium">
              Iscrizioni Aperte
            </span>
          )}
        </div>
      </div>

      {events.length > 1 && (
        <div className="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex space-x-2">
          {events.map((_, index) => (
            <button
              key={index}
              onClick={() => setCurrentEvent(index)}
              className={`w-3 h-3 rounded-full transition-colors ${
                index === currentEvent ? 'bg-white' : 'bg-white bg-opacity-50'
              }`}
            />
          ))}
        </div>
      )}
    </section>
  );
};

export default EventCarousel;