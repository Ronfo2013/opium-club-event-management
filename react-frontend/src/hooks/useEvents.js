import { useQuery, useMutation, useQueryClient } from 'react-query';
import axios from 'axios';

const fetchEvents = async () => {
  const response = await axios.get('http://localhost:8000/api/events.php');
  if (!response.data.success) {
    throw new Error(response.data.message || 'Failed to fetch events');
  }
  return response.data.data;
};

const fetchOpenEvents = async () => {
  const response = await axios.get('http://localhost:8000/api/events.php');
  if (!response.data.success) {
    throw new Error(response.data.message || 'Failed to fetch open events');
  }
  return response.data.data.filter(event => !event.chiuso);
};

export const useEvents = () => {
  return useQuery('events', fetchEvents, {
    staleTime: 5 * 60 * 1000, // 5 minutes
    cacheTime: 10 * 60 * 1000, // 10 minutes
    retry: 1,
  });
};

export const useOpenEvents = () => {
  return useQuery('openEvents', fetchOpenEvents, {
    staleTime: 5 * 60 * 1000, // 5 minutes
    cacheTime: 10 * 60 * 1000, // 10 minutes
    retry: 1,
  });
};

// Hook per eliminare un evento
export const useDeleteEvent = () => {
  const queryClient = useQueryClient();
  
  return useMutation(async (eventId) => {
    const response = await axios.post('http://localhost:8000/api/event-actions.php', {
      action: 'delete',
      event_id: eventId
    });
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to delete event');
    }
    return response.data;
  }, {
    onSuccess: () => {
      // Invalida la cache degli eventi per aggiornare la lista
      queryClient.invalidateQueries('events');
      queryClient.invalidateQueries('openEvents');
    }
  });
};

// Hook per chiudere un evento
export const useCloseEvent = () => {
  const queryClient = useQueryClient();
  
  return useMutation(async (eventId) => {
    const response = await axios.post('http://localhost:8000/api/event-actions.php', {
      action: 'close',
      event_id: eventId
    });
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to close event');
    }
    return response.data;
  }, {
    onSuccess: () => {
      queryClient.invalidateQueries('events');
      queryClient.invalidateQueries('openEvents');
    }
  });
};

// Hook per riaprire un evento
export const useReopenEvent = () => {
  const queryClient = useQueryClient();
  
  return useMutation(async (eventId) => {
    const response = await axios.post('http://localhost:8000/api/event-actions.php', {
      action: 'reopen',
      event_id: eventId
    });
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to reopen event');
    }
    return response.data;
  }, {
    onSuccess: () => {
      queryClient.invalidateQueries('events');
      queryClient.invalidateQueries('openEvents');
    }
  });
};

// Hook per creare un evento
export const useCreateEvent = () => {
  const queryClient = useQueryClient();
  
  return useMutation(async (eventData) => {
    const response = await axios.post('http://localhost:8000/api/event-actions.php', {
      action: 'create',
      ...eventData
    });
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to create event');
    }
    return response.data;
  }, {
    onSuccess: () => {
      queryClient.invalidateQueries('events');
      queryClient.invalidateQueries('openEvents');
    }
  });
};

// Hook per aggiornare un evento
export const useUpdateEvent = () => {
  const queryClient = useQueryClient();
  
  return useMutation(async ({ eventId, updates }) => {
    const response = await axios.post('http://localhost:8000/api/event-actions.php', {
      action: 'update',
      event_id: eventId,
      ...updates
    });
    if (!response.data.success) {
      throw new Error(response.data.message || 'Failed to update event');
    }
    return response.data;
  }, {
    onSuccess: () => {
      queryClient.invalidateQueries('events');
      queryClient.invalidateQueries('openEvents');
    }
  });
};